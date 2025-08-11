<?php

namespace App\Imports\ConciliationImport\Jobs;

use App\Events\ImportProgressEvent;
use App\Imports\ConciliationImport\Services\CsvValidationService;
use App\Imports\ConciliationImport\Traits\ImportHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique; // Importar la interfaz
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Throwable;
use App\Services\CacheService; // Importar tu CacheService
use App\Models\ProcessBatch; // Importar el modelo ProcessBatch

class ProcessCsvImportJob implements ShouldQueue, ShouldBeUnique // Implementar la interfaz
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, ImportHelper;

    public string $filePath;
    public string $batchId;
    public int $totalRows;

    public int $timeout = 3600;
    public int $tries = 3;

    // Definir el ID único para el Job
    public function uniqueId(): string
    {
        return $this->batchId;
    }

    // Opcional: tiempo durante el cual el Job debe ser único (en segundos)
    public function uniqueFor(): int
    {
        return 3600; // Por ejemplo, 1 hora. Ajusta según sea necesario.
    }

    public function __construct(string $filePath, string $batchId, int $totalRows)
    {
        $this->filePath = $filePath;
        $this->batchId = $batchId;
        $this->totalRows = $totalRows;
        $this->currentBatchId = $batchId;
        $this->totalRowsForJobProgress = $totalRows; // Asignar al trait
    }

    public function handle(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        Log::info("Iniciando Job de importación de CSV para batch ID: {$this->batchId}");
        $this->startBenchmark();

        try {
            Redis::hset("batch:{$this->batchId}:metadata", 'status', 'active');
            Redis::hset("batch:{$this->batchId}:metadata", 'started_at', now()->toDateTimeString());

            // Estado inicial del Job
            $this->dispatchProgressEvent(0, 'Iniciando importación', 'queued', 'Preparando...');

            // Paso 1: Extracción y precarga de glosa
            $this->dispatchProgressEvent(0, 'Extrayendo IDs para precarga de glosa', 'active', 'Iniciando...');
            $csvIdsForGlosaSum = $this->getUniqueIdsFromCsv($this->filePath, 'ID');
            Log::info(sprintf("Encontrados %d IDs únicos en el CSV para precarga de glosa.", count($csvIdsForGlosaSum)));
            if (!empty($csvIdsForGlosaSum)) {
                $this->preloadAuditoryGlosaForCsvIds($csvIdsForGlosaSum);
                Log::info("Precarga de datos de glosa completada.");
            } else {
                Log::warning("No se encontraron IDs en el CSV para precargar datos de auditoría para la validación de suma.");
            }
            $this->dispatchProgressEvent(0, 'Precarga de glosa completada', 'active', sprintf('%d IDs precargados', count($csvIdsForGlosaSum)));

            // Paso 2: Validación de cabeceras y filas (aquí es donde el 'progress' principal aumentará)
            $validationService = new CsvValidationService($this->batchId);
            $validationService->setTotalRows($this->totalRows);
            $validationService->setEventDispatcher(function($processedRecordsCurrentPhase, $action, $status, $element) {
                // Este callback es llamado por CsvValidationService y actualiza el progreso principal
                $this->dispatchProgressEvent($processedRecordsCurrentPhase, $action, $status, $element);
            });

            Log::info("Validando cabeceras y filas del CSV...");
            $errors = $validationService->validateCsv($this->filePath);

            // Paso 3: Recolección y precarga de IDs de factura, y validación de facturas completas
            $this->dispatchProgressEvent($this->totalRows, 'Recolectando IDs de factura únicos', 'active', 'Iniciando...');
            $uniqueFacturaIdsFromCsv = Redis::smembers("csv_unique_factura_ids:{$this->batchId}");
            Log::info(sprintf("Encontrados %d FACTURA_ID únicos en el CSV.", count($uniqueFacturaIdsFromCsv)));
            if (!empty($uniqueFacturaIdsFromCsv)) {
                $this->preloadDbFacturaGlosaCounts($uniqueFacturaIdsFromCsv);
                Log::info("Precarga de conteos de factura completada.");
            } else {
                Log::warning("No se encontraron FACTURA_ID en el CSV para precargar conteos de factura completa.");
            }
            $this->dispatchProgressEvent($this->totalRows, 'Precarga de conteos de factura completada', 'active', sprintf('%d FACTURA_ID precargados', count($uniqueFacturaIdsFromCsv)));

            Log::info("Realizando validación de facturas completas...");
            // La función performFacturaCompletaValidation ya despacha su propio progreso detallado
            $this->performFacturaCompletaValidation($validationService);
            Log::info("Validación de facturas completas finalizada.");

            // Recopilar todos los errores
            $errors = array_merge($errors, $validationService->getErrors());
            $errorCount = (string) Redis::llen("import_errors:{$this->batchId}");

            if (!empty($errors)) {
                Log::error('Validation errors found:');
                $this->dispatchProgressEvent($this->totalRows, 'Errores de validación encontrados', 'failed', (string)$errorCount . ' errores');
                $this->storeErrorsFromRedis();
                Redis::hset("batch:{$this->batchId}:metadata", 'status', 'failed'); // Cambiado a 'failed'
                Redis::hset("batch:{$this->batchId}:metadata", 'completed_at', now()->toDateTimeString());

                // Actualizar el estado y metadata en la tabla process_batches
                $processBatch = ProcessBatch::where('batch_id', $this->batchId)->first();
                if ($processBatch) {
                    $metadata = json_decode($processBatch->metadata, true);
                    $metadata['completed_at'] = now()->toDateTimeString();
                    $processBatch->update([
                        'status' => 'failed',
                        'error_count' => (int)$errorCount,
                        'metadata' => json_encode($metadata),
                    ]);
                }
                Log::info("Job de importación finalizado con errores para batch ID: {$this->batchId}. Errores: {$errorCount}");
                return;
            }

            // Paso 4: Importación de datos
            Log::info('CSV headers and rows are valid. Proceeding with import...');
            $this->dispatchProgressEvent($this->totalRows, 'Importando datos', 'finalizing', 'Iniciando...');
            $this->import11ConcurrentCsv($this->filePath); // Corregido el nombre de la función

            $this->dispatchProgressEvent($this->totalRows, 'Importación completada', 'completed', 'Finalizado');
            Redis::hset("batch:{$this->batchId}:metadata", 'status', 'completed');
            Redis::hset("batch:{$this->batchId}:metadata", 'completed_at', now()->toDateTimeString());

            // Actualizar el estado y metadata en la tabla process_batches
            $processBatch = ProcessBatch::where('batch_id', $this->batchId)->first();
            if ($processBatch) {
                $metadata = json_decode($processBatch->metadata, true);
                $metadata['completed_at'] = now()->toDateTimeString();
                $processBatch->update([
                    'status' => 'completed',
                    'error_count' => 0,
                    'metadata' => json_encode($metadata),
                ]);
            }
            Log::info("Job de importación completado exitosamente para batch ID: {$this->batchId}");

        } catch (Throwable $e) {
            Log::error(get_class($e) . ' ' . Str::of($e->getMessage())->limit(100)->value());
            Log::error('Error durante la importación en Job:', [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'batch_id' => $this->batchId,
            ]);
            $this->storeErrorsFromRedis();
            $this->dispatchProgressEvent($this->totalRows, 'Error crítico', 'failed', 'Fallo inesperado');
            Redis::hset("batch:{$this->batchId}:metadata", 'status', 'failed');
            Redis::hset("batch:{$this->batchId}:metadata", 'completed_at', now()->toDateTimeString());

            // Actualizar el estado y metadata en la tabla process_batches
            $processBatch = ProcessBatch::where('batch_id', $this->batchId)->first();
            if ($processBatch) {
                $errorCount = (string) Redis::llen("import_errors:{$this->batchId}");
                $metadata = json_decode($processBatch->metadata, true);
                $metadata['completed_at'] = now()->toDateTimeString();
                $processBatch->update([
                    'status' => 'failed',
                    'error_count' => (int)$errorCount,
                    'metadata' => json_encode($metadata),
                ]);
            }
        } finally {
            $this->endBenchmark();
            Log::info("Iniciando limpieza de claves de Redis para el batch actual usando CacheService...");

            $cacheService = app(CacheService::class);

            $cacheService->clearByPrefix("import_errors:{$this->currentBatchId}");
            $cacheService->clearByPrefix("auditory_glosa:{$this->currentBatchId}:");
            $cacheService->clearByPrefix("csv_factura_total_counts:{$this->currentBatchId}");
            $cacheService->clearByPrefix("csv_factura_rows:{$this->currentBatchId}:");
            $cacheService->clearByPrefix("db_factura_total_glosa_counts:{$this->currentBatchId}");
            $cacheService->clearByPrefix("csv_unique_factura_ids:{$this->currentBatchId}");
            Redis::del("batch:{$this->currentBatchId}:imported_rows_count"); // Limpiar el contador de importación

            // NOTA: La clave batch:{$this->currentBatchId}:metadata NO se elimina aquí.
            // Su expiración se maneja en el controlador/comando que la crea.

            Log::info("Limpieza de claves de Redis para el batch ID: {$this->currentBatchId} completada.");
        }
    }

    public function fail(Throwable $exception): void
    {
        Log::error("Job de importación fallido para batch ID: {$this->batchId}", [
            'exception' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'attempt' => $this->attempts(),
        ]);
        $this->storeErrorsFromRedis();
        $this->dispatchProgressEvent(0, 'Fallo en la importación del Job', 'failed', 'Error en Job');
        Redis::hset("batch:{$this->batchId}:metadata", 'status', 'failed');
        Redis::hset("batch:{$this->batchId}:metadata", 'completed_at', now()->toDateTimeString());

        // Actualizar el estado y metadata en la tabla process_batches
        $processBatch = ProcessBatch::where('batch_id', $this->batchId)->first();
        if ($processBatch) {
            $errorCount = (string) Redis::llen("import_errors:{$this->batchId}");
            $metadata = json_decode($processBatch->metadata, true);
            $metadata['completed_at'] = now()->toDateTimeString();
            $processBatch->update([
                'status' => 'failed',
                'error_count' => (int)$errorCount,
                'metadata' => json_encode($metadata),
            ]);
        }
    }

    /**
     * Despacha un evento de progreso.
     *
     * @param int $processedRowsForMainProgress El número de filas procesadas para el cálculo del progreso principal (validación de filas).
     *                                          Para otras fases, se puede usar 0 o $this->totalRows.
     * @param string $currentAction La descripción de la acción actual.
     * @param string $backendStatus El estado del backend (active, queued, completed, failed, etc.).
     * @param string $currentActionProgressDetail Un detalle del progreso de la acción actual (ej. '10/100 IDs', 'Fila 500').
     */
    protected function dispatchProgressEvent(int $processedRowsForMainProgress, string $currentAction, string $backendStatus, string $currentActionProgressDetail): void
    {
        $errorCount = (string) Redis::llen("import_errors:{$this->batchId}");

        // El porcentaje de progreso principal se basa ÚNICAMENTE en las filas validadas.
        $progressPercentage = $this->totalRows > 0 ? round(($processedRowsForMainProgress / $this->totalRows) * 100, 2) : 0;

        Log::info(sprintf(
            "Progreso del Batch %s: %s%% (Filas Validadas) - Acción: '%s' - Detalle: '%s' - Errores: %s",
            $this->batchId,
            $progressPercentage,
            $currentAction,
            $currentActionProgressDetail,
            $errorCount
        ));

        ImportProgressEvent::dispatch(
            $this->batchId,
            (string) $processedRowsForMainProgress, // processedRecords para el evento
            $currentAction,
            $errorCount,
            $backendStatus,
            $currentActionProgressDetail // currentStudent para el evento
        );
    }
}
