<?php

namespace App\Jobs;

use App\Events\ImportProgressEvent;
use App\Traits\ImportHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Throwable;
use App\Services\CacheService; // Importar tu CacheService

class ProcessCsvImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, ImportHelper;

    public string $filePath;
    public string $batchId;
    public int $totalRows;

    public int $timeout = 3600;
    public int $tries = 3;

    public function __construct(string $filePath, string $batchId, int $totalRows)
    {
        $this->filePath = $filePath;
        $this->batchId = $batchId;
        $this->totalRows = $totalRows;
        $this->currentBatchId = $batchId; // Asegurar que el trait tenga el batchId
    }

    public function handle(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        Log::info("Iniciando Job de importación para batch ID: {$this->batchId}");
        $this->startBenchmark(); // Este método ahora usa Log::info()

        // --- INICIO BLOQUE DE DEPURACIÓN DE REDIS ---
        $testKey = 'laravel_database_test_key_for_batch_' . $this->batchId; // Aseguramos que el prefijo esté aquí
        Log::info("DEBUG REDIS: Intentando establecer clave de prueba: {$testKey}");
        Redis::set($testKey, 'test_value');
        $readValue = Redis::get($testKey);
        Log::info("DEBUG REDIS: Valor leído de {$testKey}: " . ($readValue ?? 'NULL'));

        if ($readValue === 'test_value') {
            Log::info("DEBUG REDIS: Clave de prueba establecida y leída correctamente. Intentando eliminarla...");
            $deletedCount = Redis::del($testKey);
            Log::info("DEBUG REDIS: Eliminadas {$deletedCount} claves de prueba.");
            if (Redis::exists($testKey)) {
                Log::error("DEBUG REDIS: ¡ERROR! La clave de prueba '{$testKey}' aún existe después de intentar eliminarla.");
            } else {
                Log::info("DEBUG REDIS: La clave de prueba '{$testKey}' fue eliminada exitosamente.");
            }
        } else {
            Log::error("DEBUG REDIS: ¡ERROR! No se pudo establecer o leer la clave de prueba '{$testKey}'. Posible problema de conexión/permisos.");
        }
        // --- FIN BLOQUE DE DEPURACIÓN DE REDIS ---


        try {
            Redis::hset("batch:{$this->batchId}:metadata", 'status', 'active');
            Redis::hset("batch:{$this->batchId}:metadata", 'started_at', now()->toDateTimeString());

            Log::info("Extrayendo IDs únicos del CSV para precarga de glosa...");
            $this->dispatchProgressEvent(0, 'Extrayendo IDs para precarga de glosa', 'active', '1');
            $csvIdsForGlosaSum = $this->getUniqueIdsFromCsv($this->filePath, 'ID');
            Log::info(sprintf("Encontrados %d IDs únicos en el CSV para precarga de glosa.", count($csvIdsForGlosaSum)));
            Log::info('IDs únicos extraídos del CSV para glosa (primeros 10):', array_slice($csvIdsForGlosaSum, 0, 10));

            if (!empty($csvIdsForGlosaSum)) {
                Log::info("Precargando datos de auditory_final_reports a Redis para los IDs del CSV (validación de suma)...");
                $this->dispatchProgressEvent(0, 'Precargando datos de glosa', 'active', '1');
                // Esta llamada ahora leerá de la caché maestra si existe y limpiará las claves de batch al inicio
                $this->preloadAuditoryGlosaForCsvIds($csvIdsForGlosaSum);
                Log::info("Precarga de datos de glosa completada.");
            } else {
                Log::warning("No se encontraron IDs en el CSV para precargar datos de auditoría para la validación de suma.");
            }

            $validationService = new \App\Services\CsvValidationService($this->batchId);
            $validationService->setTotalRows($this->totalRows);
            $validationService->setEventDispatcher(function($processed, $action, $status, $element) {
                $this->dispatchProgressEvent($processed, $action, $status, $element);
            });

            Log::info("Validando cabeceras y filas del CSV...");
            $errors = $validationService->validateCsv($this->filePath);

            Log::info("Obteniendo FACTURA_ID únicos del CSV para la validación de facturas completas...");
            $this->dispatchProgressEvent($this->totalRows, 'Recolectando IDs de factura únicos', 'active', (string)$this->totalRows);
            $uniqueFacturaIdsFromCsv = Redis::smembers("csv_unique_factura_ids:{$this->batchId}");
            Log::info(sprintf("Encontrados %d FACTURA_ID únicos en el CSV.", count($uniqueFacturaIdsFromCsv)));
            Log::info('FACTURA_ID únicos extraídos del CSV (primeros 10):', array_slice($uniqueFacturaIdsFromCsv, 0, 10));

            if (!empty($uniqueFacturaIdsFromCsv)) {
                Log::info("Precargando conteos de FACTURA_ID de auditory_final_reports (valor_glosa > 0) a Redis...");
                $this->dispatchProgressEvent($this->totalRows, 'Precargando conteos de factura de DB', 'active', (string)$this->totalRows);
                // Modificado para usar la nueva estrategia de caché de dos niveles y limpiar claves de batch al inicio
                $this->preloadDbFacturaGlosaCounts($uniqueFacturaIdsFromCsv);
                Log::info("Precarga de conteos de factura completada.");
            } else {
                Log::warning("No se encontraron FACTURA_ID en el CSV para precargar conteos de factura completa.");
            }

            Log::info("Realizando validación de facturas completas...");
            $this->dispatchProgressEvent($this->totalRows, 'Realizando validación de facturas completas', 'active', (string)$this->totalRows);
            $this->performFacturaCompletaValidation($validationService);
            Log::info("Validación de facturas completas finalizada.");

            $errors = array_merge($errors, $validationService->getErrors());
            $errorCount = (string) Redis::llen("import_errors:{$this->batchId}");

            if (!empty($errors)) {
                Log::error('Validation errors found:');
                $this->dispatchProgressEvent($this->totalRows, 'Errores de validación encontrados', 'completed_with_errors', (string)$this->totalRows);
                $this->storeErrorsFromRedis();
                Redis::hset("batch:{$this->batchId}:metadata", 'status', 'completed_with_errors');
                Redis::hset("batch:{$this->batchId}:metadata", 'completed_at', now()->toDateTimeString());
                Log::info("Job de importación finalizado con errores para batch ID: {$this->batchId}. Errores: {$errorCount}");
                return;
            }

            Log::info('CSV headers and rows are valid. Proceeding with import...');
            $this->dispatchProgressEvent($this->totalRows, 'Importando datos', 'finalizing', (string)$this->totalRows);
            $this->import11Concurrent($this->filePath);

            $this->dispatchProgressEvent($this->totalRows, 'Importación completada', 'completed', (string)$this->totalRows);
            Redis::hset("batch:{$this->batchId}:metadata", 'status', 'completed');
            Redis::hset("batch:{$this->batchId}:metadata", 'completed_at', now()->toDateTimeString());
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
            $this->dispatchProgressEvent($this->totalRows, 'Error crítico', 'failed', (string)$this->totalRows);
            Redis::hset("batch:{$this->batchId}:metadata", 'status', 'failed');
            Redis::hset("batch:{$this->batchId}:metadata", 'completed_at', now()->toDateTimeString());
        } finally {
            $this->endBenchmark(); // Este método ahora usa Log::info()
            Log::info("Iniciando limpieza de claves de Redis para el batch actual usando CacheService...");

            $cacheService = app(CacheService::class); // Resolver CacheService desde el contenedor

            // Limpiar claves específicas del batch usando clearByPrefix
            // Nota: clearByPrefix ya añade el '*' al final, así que no lo incluyas en el prefijo aquí.
            // Para patrones que terminan en ':', asegúrate de que el prefijo pasado a clearByPrefix lo incluya.
            $cacheService->clearByPrefix("import_errors:{$this->currentBatchId}");
            $cacheService->clearByPrefix("auditory_glosa:{$this->currentBatchId}:");
            $cacheService->clearByPrefix("csv_factura_total_counts:{$this->currentBatchId}");
            $cacheService->clearByPrefix("csv_factura_rows:{$this->currentBatchId}:");
            $cacheService->clearByPrefix("db_factura_total_glosa_counts:{$this->currentBatchId}");
            $cacheService->clearByPrefix("csv_unique_factura_ids:{$this->currentBatchId}");
            $cacheService->clearByPrefix("batch:{$this->currentBatchId}:metadata");

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
        $this->dispatchProgressEvent(0, 'Fallo en la importación del Job', 'failed', '0');
        Redis::hset("batch:{$this->batchId}:metadata", 'status', 'failed');
        Redis::hset("batch:{$this->batchId}:metadata", 'completed_at', now()->toDateTimeString());
    }

    protected function dispatchProgressEvent(int $processedRecords, string $currentAction, string $backendStatus, string $currentElement): void
    {
        $errorCount = (string) Redis::llen("import_errors:{$this->batchId}");

        // Calcular el porcentaje de progreso
        $progressPercentage = $this->totalRows > 0 ? round(($processedRecords / $this->totalRows) * 100, 2) : 0;

        // Registrar en el log
        Log::info(sprintf(
            "Progreso del Batch %s: %s%% - Acción: '%s' - Elemento actual: '%s' - Errores: %s",
            $this->batchId,
            $progressPercentage,
            $currentAction,
            $currentElement,
            $errorCount
        ));

        ImportProgressEvent::dispatch(
            $this->batchId,
            (string) $processedRecords,
            $currentAction,
            $errorCount,
            $backendStatus,
            $currentElement
        );
    }
}
