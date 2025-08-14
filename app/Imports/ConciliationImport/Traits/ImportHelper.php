<?php

namespace App\Imports\ConciliationImport\Traits;

use App\Imports\ConciliationImport\Services\CsvValidationService;
use App\Models\AuditoryFinalReport;
use App\Models\ProcessBatch;
use App\Services\CacheService;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
// Añadido: Importar el evento de progreso
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use SplFileObject; // Añadido: Importar SplFileObject

trait ImportHelper
{
    protected float $benchmarkStartTime;

    protected int $benchmarkStartMemory;

    protected int $startQueries;

    protected string $currentBatchId;

    protected int $totalRowsForJobProgress; // Añadido para mantener el total de filas del archivo original para el cálculo de progreso global

    protected function startBenchmark(string $batchId): void
    {
        $this->benchmarkStartTime = microtime(true);
        $this->benchmarkStartMemory = memory_get_usage(); // Corregido: memory_usage() a memory_get_usage()
        DB::enableQueryLog();
        $this->startQueries = DB::select("SHOW SESSION STATUS LIKE 'Questions'")[0]->Value;
    }

    protected function endBenchmark(string $batchId): void
    {
        $processBatch = ProcessBatch::where('batch_id', $batchId)->first();
        $executionTime = microtime(true) - $this->benchmarkStartTime;
        $memoryUsage = round((memory_get_usage() - $this->benchmarkStartMemory) / 1024 / 1024, 2);
        $queriesCount = DB::select("SHOW SESSION STATUS LIKE 'Questions'")[0]->Value - (isset($this->startQueries) ? $this->startQueries : 0) - 1;

        $formattedTime = match (true) {
            $executionTime >= 60 => sprintf('%dm %ds', floor($executionTime / 60), $executionTime % 60),
            $executionTime >= 1 => round($executionTime, 2) . 's',
            default => round($executionTime * 1000) . 'ms',
        };

        // Registrar las métricas en el log (funcionalidad original)
        Log::info(sprintf(
            '⚡ Batch %s | TIME: %s | MEM: %sMB | SQL: %s | ROWS: %s',
            $batchId, // Cambiado de $this->currentBatchId a $batchId para consistencia
            $formattedTime,
            $memoryUsage,
            number_format($queriesCount),
            number_format($processBatch->total_records)
        ));

        // Obtener el metadata actual
        $existingMetadata = $processBatch->metadata;

        // Decodificar el metadata existente (si existe) o usar un array vacío
        $metadata = $existingMetadata && json_last_error() === JSON_ERROR_NONE ? json_decode($existingMetadata, true) : [];

        // Agregar las nuevas métricas bajo una clave específica
        $metadata['performance'] = [
            'time' => $formattedTime,
            'memory_mb' => $memoryUsage,
            'sql_queries' => $queriesCount,
        ];

        // Actualizar el campo metadata con los datos combinados
        $processBatch->update([
            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
        ]);
    }



    /**
     * Precarga los conteos de FACTURA_ID de AuditoryFinalReport (donde valor_glosa > 0) a Redis.
     * Almacena conteos totales de FACTURA_ID.
     * Ahora lee de la caché maestra global de Redis, y la precarga incrementalmente si faltan IDs.
     */
    protected function preloadDbFacturaGlosaCounts(array $fileFacturaIds): void
    {
        $redisMasterKey = 'db_factura_glosa_counts_master';
        $cacheService = app(CacheService::class);

        // Log::info("DEBUG PRELOAD FACTURA: Iniciando precarga para batch ID: {$this->currentBatchId}. IDs de factura a procesar: " . count($fileFacturaIds));
        // Log::info("DEBUG PRELOAD FACTURA: Primeros 10 IDs de factura del archivo: " . json_encode(array_slice($fileFacturaIds, 0, 10)));

        $cacheService->clearByPrefix("db_factura_total_glosa_counts:{$this->currentBatchId}");
        // Log::info("DEBUG PRELOAD FACTURA: Limpiando claves de 'db_factura_total_glosa_counts' para el batch actual al inicio de la precarga.");

        $preloadStartTime = microtime(true);
        $count = 0;
        $foundFacturaIdsInMasterCache = [];
        $facturaIdsToLoadFromDb = [];
        $chunkSize = 1000;

        // Paso 1: Identificar qué IDs de factura del archivo ya están en la caché maestra y cuáles faltan
        if (Redis::connection('redis_6380')->exists($redisMasterKey)) {
            // Log::info("DEBUG PRELOAD FACTURA: La caché maestra '{$redisMasterKey}' ya existe. Verificando IDs de factura del archivo...");
            foreach (array_chunk($fileFacturaIds, $chunkSize) as $facturaIdChunk) {
                $facturaIdChunk = array_map('strval', $facturaIdChunk); // Asegurar que los IDs en el chunk son cadenas
                $countsValues = Redis::connection('redis_6380')->hmget($redisMasterKey, $facturaIdChunk);
                foreach ($facturaIdChunk as $index => $facturaId) {
                    if (! is_null($countsValues[$index])) {
                        $foundFacturaIdsInMasterCache[] = $facturaId;
                    } else {
                        $facturaIdsToLoadFromDb[] = $facturaId;
                    }
                }
            }
            // Log::info(sprintf("DEBUG PRELOAD FACTURA: %d IDs de factura del archivo ya están en caché maestra. %d IDs necesitan ser cargados de DB.", count($foundFacturaIdsInMasterCache), count($facturaIdsToLoadFromDb)));
        } else {
            // Log::info("DEBUG PRELOAD FACTURA: La caché maestra '{$redisMasterKey}' no existe. Todos los IDs de factura del archivo necesitan ser cargados de DB.");
            $facturaIdsToLoadFromDb = $fileFacturaIds;
        }

        // Paso 2: Cargar los IDs de factura faltantes de la DB a la caché maestra
        if (! empty($facturaIdsToLoadFromDb)) {
            // Log::info(sprintf("DEBUG PRELOAD FACTURA: Cargando %d IDs de factura (con glosa > 0) desde la base de datos a la caché maestra...", count($facturaIdsToLoadFromDb)));
            // Log::info("DEBUG PRELOAD FACTURA: Primeros 10 IDs de factura a cargar de DB: " . json_encode(array_slice($facturaIdsToLoadFromDb, 0, 10)));
            $dbLoadCount = 0;
            // $dbLoadStartTime = microtime(true);
            $dbPipeline = Redis::connection('redis_6380')->pipeline();

            foreach (array_chunk($facturaIdsToLoadFromDb, $chunkSize) as $facturaIdChunkForDb) {
                $facturaIdChunkForDb = array_map('strval', $facturaIdChunkForDb); // Asegurar que los IDs para whereIn son cadenas
                $dbResults = AuditoryFinalReport::select('factura_id', DB::raw('COUNT(*) as total_count'))
                    ->whereIn('factura_id', $facturaIdChunkForDb)
                    ->where('valor_glosa', '>', 0)
                    ->groupBy('factura_id')
                    ->orderBy('factura_id')
                    ->get(); // Usar get() para procesar el chunk completo

                // Log::info(sprintf("DEBUG PRELOAD FACTURA DB: Recibidos %d resultados de la DB para este chunk.", $dbResults->count()));
                // Log::info("DEBUG PRELOAD FACTURA DB: Primeros 5 IDs de factura encontrados en DB para este chunk: " . json_encode(array_slice($dbResults->pluck('factura_id')->toArray(), 0, 5)));

                foreach ($dbResults as $result) {
                    $dbPipeline->hset($redisMasterKey, (string) $result->factura_id, (string) $result->total_count);
                    $dbLoadCount++;
                }
            }
            $dbPipeline->execute();
            Redis::connection('redis_6380')->expire($redisMasterKey, 60 * 60 * 24 * 180); // 6 meses
            // $dbLoadEndTime = microtime(true);
            // Log::info(sprintf("DEBUG PRELOAD FACTURA DB: Carga de %d IDs de factura desde DB a caché maestra completada en %.2f segundos.", $dbLoadCount, ($dbLoadEndTime - $dbLoadStartTime)));
        } else {
            // Log::info("DEBUG PRELOAD FACTURA: No hay IDs de factura del archivo que necesiten ser cargados de la base de datos a la caché maestra.");
        }

        // Paso 3: Poblar la caché específica del batch desde la caché maestra (ahora actualizada)
        $finalFoundFacturaIdsForBatch = [];
        $pipeline = Redis::connection('redis_6380')->pipeline();
        $redisHashKey = "db_factura_total_glosa_counts:{$this->currentBatchId}";

        foreach (array_chunk($fileFacturaIds, $chunkSize) as $facturaIdChunk) {
            $facturaIdChunk = array_map('strval', $facturaIdChunk); // Asegurar que los IDs en el chunk son cadenas
            $countsValues = Redis::connection('redis_6380')->hmget($redisMasterKey, $facturaIdChunk);
            foreach ($facturaIdChunk as $index => $facturaId) {
                if (! is_null($countsValues[$index])) {
                    $pipeline->hset($redisHashKey, (string) $facturaId, (string) $countsValues[$index]);
                    $finalFoundFacturaIdsForBatch[] = $facturaId;
                    $count++;
                }
            }
        }
        $pipeline->execute();

        $preloadEndTime = microtime(true);
        // Log::info(sprintf("DEBUG PRELOAD FACTURA: Resumen de precarga de conteos de factura (para batch): %d registros en %.2f segundos.", $count, ($preloadEndTime - $preloadStartTime)));

        $notFoundFacturaIds = array_diff($fileFacturaIds, $finalFoundFacturaIdsForBatch);
        if (! empty($notFoundFacturaIds)) { // CORREGIDO: Usar $notFoundFacturaIds
            Log::warning(sprintf("ATENCIÓN PRELOAD FACTURA: %d IDs de factura del archivo no se encontraron en la caché maestra '{$redisMasterKey}' y no fueron precargados para este batch. Primeros 10 IDs no encontrados:", count($notFoundFacturaIds))); // CORREGIDO: Usar $notFoundFacturaIds
            Log::warning(json_encode(array_slice($notFoundFacturaIds, 0, 10))); // CORREGIDO: Usar $notFoundFacturaIds
        } else {
            // Log::info("DEBUG PRELOAD FACTURA: Todos los IDs de factura del archivo se encontraron en la caché maestra '{$redisMasterKey}' y fueron precargados para este batch.");
        }
    }

    /**
     * Realiza la validación de "Facturas Completas".
     * Compara el número de registros de una factura en el archivo con el número de glosas > 0 en la DB.
     *
     * @param  CsvValidationService  $validationService  Instancia del servicio de validación.
     */
    protected function performFacturaCompletaValidation(CsvValidationService $validationService): void
    {
        // Log::info("Iniciando performFacturaCompletaValidation para batch ID: {$this->currentBatchId}");

        $uniqueFacturaIdsFromCsv = Redis::connection('redis_6380')->smembers("csv_unique_factura_ids:{$this->currentBatchId}");
        $totalFacturaIds = count($uniqueFacturaIdsFromCsv);
        $processedFacturaIds = 0;
        $dispatchInterval = max(1, floor($totalFacturaIds / 100)); // Despachar al menos 100 veces

        foreach ($uniqueFacturaIdsFromCsv as $facturaId) {
            $processedFacturaIds++;

            // Obtener el conteo de filas para esta factura en el archivo de importación
            $fileFacturaCount = (int) Redis::connection('redis_6380')->hget("csv_factura_total_counts:{$this->currentBatchId}", $facturaId);

            // Obtener el conteo de glosas > 0 para esta factura en la base de datos (precargado)
            $dbGlosaCount = (int) Redis::connection('redis_6380')->hget("db_factura_total_glosa_counts:{$this->currentBatchId}", $facturaId);

            // Log::debug(sprintf(
            //     "DEBUG FACTURA COMPLETA: Factura ID '%s' - Archivo: %d, DB Glosa: %d",
            //     $facturaId,
            //     $fileFacturaCount,
            //     $dbGlosaCount
            // ));

            if ($fileFacturaCount !== $dbGlosaCount) {
                // Recuperar los números de fila asociados a esta factura para el mensaje de error
                $rowNumbersJson = Redis::connection('redis_6380')->lrange("csv_factura_rows:{$this->currentBatchId}:{$facturaId}", 0, -1);
                $rowNumbers = array_map('intval', $rowNumbersJson);
                $firstRow = ! empty($rowNumbers) ? min($rowNumbers) : 0; // Usar la primera fila para el error

                $errorMessage = sprintf(
                    "La factura ID '%s' tiene %d registros en el archivo, pero %d glosas > 0 en la base de datos. No coinciden.",
                    $facturaId,
                    $fileFacturaCount,
                    $dbGlosaCount
                );
                $validationService->addError(
                    $firstRow, // Usar el número de la primera fila de la factura para el error
                    'FACTURA_ID',
                    $errorMessage,
                    'factura_completa_mismatch',
                    $facturaId,
                    json_encode(['file_count' => $fileFacturaCount, 'db_glosa_count' => $dbGlosaCount, 'rows_in_file' => $rowNumbers])
                );
                // Log::warning("ERROR FACTURA COMPLETA: " . $errorMessage);
            }

            // Despachar evento de progreso periódicamente
            if ($processedFacturaIds % $dispatchInterval === 0 || $processedFacturaIds === $totalFacturaIds) {
                if (method_exists($this, 'dispatchProgressEvent')) {
                    $this->dispatchProgressEvent(
                        $this->totalRowsForJobProgress, // Mantiene el progreso principal en 100% (de validación de filas)
                        'Validando facturas completas',
                        'active',
                        sprintf('%d/%d facturas validadas', $processedFacturaIds, $totalFacturaIds) // Detalle en currentStudent
                    );
                }
            }
        }
        // Log::info("Finalizado performFacturaCompletaValidation para batch ID: {$this->currentBatchId}");
    }

    /**
     * Retrieves errors from Redis for the current batch and inserts them into the database.
     */
    protected function storeErrorsFromRedis(): void
    {
        $errorKey = "import_errors:{$this->currentBatchId}";
        // Log::info("Attempting to retrieve errors from Redis key: {$errorKey}");

        $rawErrors = Redis::connection('redis_6380')->lrange($errorKey, 0, -1);

        if (empty($rawErrors)) {
            // Log::info("No errors found in Redis for batch ID: {$this->currentBatchId}");
            return;
        }

        $errorsToInsert = [];
        foreach ($rawErrors as $errorJson) {
            $decodedError = json_decode($errorJson, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $errorsToInsert[] = $decodedError;
            } else {
                Log::error('Failed to decode JSON error from Redis: ' . json_last_error_msg(), ['json' => $errorJson]);
            }
        }

        if (! empty($errorsToInsert)) {
            $chunkSize = 500;
            // $totalErrors = count($errorsToInsert);
            // Log::info(sprintf('Found %d errors to insert into DB for batch ID: %s. Inserting in chunks of %d.', $totalErrors, $this->currentBatchId, $chunkSize));

            foreach (array_chunk($errorsToInsert, $chunkSize) as $chunk) {
                DB::transaction(function () use ($chunk) {
                    try {
                        DB::table('process_batches_errors')->insert($chunk);
                        // Log::info(sprintf('Successfully inserted a chunk of %d errors into process_batches_errors table.', count($chunk)));
                    } catch (\Exception $e) {
                        Log::error('Failed to bulk insert errors into process_batches_errors: ' . $e->getMessage());
                        Log::error('Database insertion failed for errors chunk:', [
                            'exception' => $e->getMessage(),
                            'code' => $e->getCode(),
                            'trace' => $e->getTraceAsString(),
                            'errors_attempted_to_insert' => $chunk,
                        ]);
                        throw $e;
                    }
                    // Log::info(sprintf('Stored %d validation errors to process_batches_errors table.', $totalErrors));
                    // Log::info('Finished inserting all errors into process_batches_errors table.');
                });
            }

            // $persistedErrorsCount = DB::table('process_batches_errors')
            //     ->where('batch_id', $this->currentBatchId)
            //     ->count();
            // Log::info(sprintf('DEBUG DB: %d errores encontrados en la DB para batch ID: %s inmediatamente después de la inserción.', $persistedErrorsCount, $this->currentBatchId));
        } else {
            // Log::info("No valid errors to insert after decoding for batch ID: {$this->currentBatchId}");
        }
        Redis::connection('redis_6380')->del($errorKey);
    }

    /**
     * Realiza la importación concurrente de datos desde un archivo CSV.
     *
     * @param  string  $filePath  Ruta completa al archivo CSV.
     */
    protected function import11ConcurrentCsv(string $filePath): void
    {
        // Log::info("iniciando import11Concurrent desde CSV");
        $now = now()->format('Y-m-d H:i:s');
        $numberOfProcesses = 10;
        $tasks = [];

        $file = new SplFileObject($filePath, 'r');
        $file->setFlags(SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD);
        $file->fgets(); // Skip header

        $totalRows = 0;
        foreach ($file as $line) {
            $totalRows++;
        }
        $file = null; // Reset file pointer
        Log::info("Total de filas en el CSV: {$totalRows}");

        $batchId = $this->currentBatchId;
        $totalRowsForEvent = $this->totalRowsForJobProgress; // Total de filas del archivo para el progreso principal

        // Inicializar contador de Redis para filas importadas
        $redis = Redis::connection('redis_6380');
        $redis->set("batch:{$batchId}:imported_rows_count", 0);
        $redis->expire("batch:{$batchId}:imported_rows_count", 3600 * 24); // Expiración de 24 horas
        Log::info("Contador de filas importadas inicializado en Redis para batch: {$batchId}");

        for ($i = 0; $i < $numberOfProcesses; $i++) {
            $tasks[] = function () use ($filePath, $i, $numberOfProcesses, $now, $batchId) {
                DB::reconnect();
                $handle = fopen($filePath, 'r');
                fgets($handle); // Skip header
                $currentLine = 0;
                $dataToSave = [];
                $invoicesToUpdate = [];

                while (($line = fgets($handle)) !== false) {
                    if ($currentLine++ % $numberOfProcesses !== $i) {
                        continue;
                    }
                    $row = str_getcsv($line, ';');

                    // Aplica trim y ensureUtf8 a todo el arreglo $row
                    $row = array_map('trim', $row);
                    $row = ensureUtf8($row);

                    $dataToSave[] = [
                        'id' => (string) Str::uuid(),
                        'auditory_final_report_id' => (string) $row[0],
                        'invoice_audit_id' => (string) $row[1],
                        'response_status' => (string) $row[29],
                        'autorization_number' => (string) $row[30],
                        'accepted_value_ips' => (float) str_replace(',', '.', $row[32]),
                        'accepted_value_eps' => (float) str_replace(',', '.', $row[33]),
                        'eps_ratified_value' => (float) str_replace(',', '.', $row[34]),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    // Acumular sumas en Redis para invoice_audit_id
                    $invoice_audit_id = (string) $row[1];
                    $accepted_value_ips = is_numeric(str_replace(',', '.', $row[32])) ? (float) str_replace(',', '.', $row[32]) : 0.0;
                    $accepted_value_eps = is_numeric(str_replace(',', '.', $row[33])) ? (float) str_replace(',', '.', $row[33]) : 0.0;
                    $eps_ratified_value = is_numeric(str_replace(',', '.', $row[34])) ? (float) str_replace(',', '.', $row[34]) : 0.0;

                    // Validación adicional para valores no numéricos
                    if (!is_numeric(str_replace(',', '.', $row[32])) || !is_numeric(str_replace(',', '.', $row[33])) || !is_numeric(str_replace(',', '.', $row[34]))) {
                        Log::warning("Valores no numéricos en la línea {$currentLine} para invoice_audit_id {$invoice_audit_id}: " . json_encode([
                            'VALOR_ACEPTADO_POR_IPS' => $row[32],
                            'VALOR_ACEPTADO_POR_EPS' => $row[33],
                            'VALOR_RATIFICADO_EPS' => $row[34],
                        ]));
                    }

                    // Usar Redis para acumular sumas
                    $redis = Redis::connection('redis_6380');
                    $redisKey = "batch:{$batchId}:sums:{$invoice_audit_id}";
                    $redis->hincrbyfloat($redisKey, 'sum_accepted_value_ips', $accepted_value_ips);
                    $redis->hincrbyfloat($redisKey, 'sum_accepted_value_eps', $accepted_value_eps);
                    $redis->hincrbyfloat($redisKey, 'sum_eps_ratified_value', $eps_ratified_value);
                    $redis->expire($redisKey, 3600 * 24);

                    // Log para verificar acumulación en Redis
                    Log::debug("Acumulado en Redis para clave {$redisKey}: IPS={$accepted_value_ips}, EPS={$accepted_value_eps}, RATIFIED={$eps_ratified_value}");

                    // Recolectar invoice_audit_id para actualizar status
                    $invoicesToUpdate[] = $invoice_audit_id;

                    if (count($dataToSave) === 1000) {
                        // Insertar en conciliation_results
                        DB::table('conciliation_results')->insert($dataToSave);

                        // Actualizar conciliation_invoices en lotes (solo status)
                        if (!empty($invoicesToUpdate)) {
                            DB::transaction(function () use ($invoicesToUpdate) {
                                DB::table('conciliation_invoices')
                                    ->whereIn('invoice_audit_id', array_unique($invoicesToUpdate))
                                    ->update(['status' => 'CONCILIATION_INVOICE_EST_002']);
                            });
                            Log::info("Actualizados " . count(array_unique($invoicesToUpdate)) . " registros en conciliation_invoices (status)");
                        }

                        $dataToSave = [];
                        $invoicesToUpdate = [];
                    }

                    // Incrementar contador global de Redis para filas importadas
                    $redis->incr("batch:{$batchId}:imported_rows_count");
                }

                // Insertar y actualizar registros restantes
                if (!empty($dataToSave)) {
                    DB::table('conciliation_results')->insert($dataToSave);
                    if (!empty($invoicesToUpdate)) {
                        DB::transaction(function () use ($invoicesToUpdate) {
                            DB::table('conciliation_invoices')
                                ->whereIn('invoice_audit_id', array_unique($invoicesToUpdate))
                                ->update(['status' => 'CONCILIATION_INVOICE_EST_002']);
                        });
                        Log::info("Actualizados " . count(array_unique($invoicesToUpdate)) . " registros en conciliation_invoices (status)");
                    }
                }

                fclose($handle);
                return true;
            };
        }
        Concurrency::run($tasks);

        // Actualizar sumas en conciliation_invoices desde Redis (optimizado en lotes)
        $this->processRedisSumsConcurrently($batchId);

        // Asegurar que el evento final de importación se despacha después de que todas las tareas concurrentes completen
        $finalImportedCount = (string) $redis->get("batch:{$batchId}:imported_rows_count");
        Log::info("Total de filas importadas según Redis: {$finalImportedCount}");
        $this->dispatchProgressEvent(
            $totalRowsForEvent,
            'Importación completada',
            'completed',
            sprintf('%s/%d registros importados', $finalImportedCount, $totalRows)
        );

        // Limpiar el contador de filas importadas de Redis
        $redis->del("batch:{$batchId}:imported_rows_count");
        Log::info("Contador de filas importadas eliminado de Redis para batch {$batchId}");

        // Limpiar las claves de sumas de Redis
        if (!empty($keys)) {
            $redis->del($keys);
            Log::info("Limpiadas " . count($keys) . " claves de sumas en Redis para batch {$batchId}");
        }
    }

    /**
     * Procesa las sumas acumuladas en Redis y actualiza la tabla conciliation_invoices de manera concurrente.
     *
     * @param string $batchId ID del lote actual
     * @param int $numberOfProcesses Número de procesos concurrentes a utilizar (opcional)
     * @param int $chunkSize Tamaño de los lotes para actualización (opcional)
     */
    protected function processRedisSumsConcurrently(
        string $batchId,
        int $numberOfProcesses = 10,
        int $chunkSize = 1000
    ): void {
        $redis = Redis::connection('redis_6380');

        // Obtener todas las claves de sumas para este batch (con prefijo correcto)
        $pattern = "laravel_database_batch:{$batchId}:sums:*";
        $keys = $redis->keys($pattern);
        Log::info("Buscando claves con patrón: {$pattern}");
        Log::info("Encontradas " . count($keys) . " claves de sumas en Redis para batch {$batchId}");

        if (empty($keys)) {
            // Intento alternativo sin el prefijo laravel_database_
            $pattern = "batch:{$batchId}:sums:*";
            $keys = $redis->keys($pattern);
            Log::info("Intento alternativo con patrón: {$pattern}");
            Log::info("Encontradas " . count($keys) . " claves alternativas");

            if (empty($keys)) {
                Log::warning("No se encontraron claves de sumas en Redis para el batch {$batchId}");
                // Debug adicional: mostrar algunas claves existentes para diagnóstico
                $sampleKeys = $redis->keys("*");
                Log::info("Claves de muestra en Redis: " . json_encode(array_slice($sampleKeys, 0, 5)));
                return;
            }
        }

        // Preparar tareas concurrentes
        $tasks = [];
        $chunks = array_chunk($keys, ceil(count($keys) / $numberOfProcesses));

        foreach ($chunks as $chunkIndex => $chunkKeys) {
            $tasks[] = function () use ($redis, $batchId, $chunkKeys, $chunkSize, $chunkIndex) {
                DB::reconnect();
                $processedInThisProcess = 0;
                $startTime = microtime(true);

                foreach ($chunkKeys as $key) {
                    try {
                        $realKey = str_replace('laravel_database_', '', $key);
                        $sums = $redis->hgetall($realKey);

                        // Extraer invoice_audit_id correctamente
                        $invoiceAuditId = str_replace(
                            ["laravel_database_batch:{$batchId}:sums:", "batch:{$batchId}:sums:"],
                            "",
                            $key
                        );

                        Log::debug("Procesando clave: {$realKey} para invoice: {$invoiceAuditId}");
                        Log::debug("Valores obtenidos: " . json_encode($sums));

                        // Procesamiento directo de valores
                        $sumAcceptedValueIps = isset($sums['sum_accepted_value_ips'])
                            ? (float)$sums['sum_accepted_value_ips']
                            : 0.0;

                        $sumAcceptedValueEps = isset($sums['sum_accepted_value_eps'])
                            ? (float)$sums['sum_accepted_value_eps']
                            : 0.0;

                        $sumEpsRatifiedValue = isset($sums['sum_eps_ratified_value'])
                            ? (float)$sums['sum_eps_ratified_value']
                            : 0.0;

                        // Actualizar la base de datos
                        $updated = DB::table('conciliation_invoices')
                            ->where('invoice_audit_id', $invoiceAuditId)
                            ->update([
                                'sum_accepted_value_ips' => $sumAcceptedValueIps,
                                'sum_accepted_value_eps' => $sumAcceptedValueEps,
                                'sum_eps_ratified_value' => $sumEpsRatifiedValue,
                            ]);

                        if ($updated === 0) {
                            Log::warning("No se actualizó ningún registro para invoice_audit_id: {$invoiceAuditId}");
                        }

                        $processedInThisProcess++;
                    } catch (\Exception $e) {
                        Log::error("Error procesando clave {$key}: " . $e->getMessage());
                        throw $e;
                    }
                }

                $elapsed = round(microtime(true) - $startTime, 2);
                Log::info("Proceso {$chunkIndex} completado. Registros procesados: {$processedInThisProcess}. Tiempo: {$elapsed}s");
                return $processedInThisProcess;
            };
        }

        Log::info("Iniciando {$numberOfProcesses} procesos para actualizar sumas...");
        $results = Concurrency::run($tasks);

        $totalProcessed = array_sum($results);
        Log::info("Actualización de sumas completada. Total registros actualizados: {$totalProcessed}");

        // Limpiar solo si se procesaron correctamente
        if ($totalProcessed > 0) {
            $redis->del($keys);
            Log::info("Eliminadas " . count($keys) . " claves de sumas de Redis para el batch {$batchId}");
        }
    }

    public function getUniqueValuesFromCsv(string $filePath, $columnNames): array
    {
        $columnNames = is_array($columnNames) ? $columnNames : [$columnNames];
        $uniqueValues = array_fill_keys($columnNames, []);

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            Log::error('Error: No se pudo abrir el archivo CSV para extraer valores.', ['path' => $filePath]);
            return $uniqueValues;
        }

        $headers = fgetcsv($handle, 0, ';');
        if ($headers && !empty($headers[0])) {
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        }

        if ($headers === false || empty($headers)) {
            Log::error('Error: El archivo CSV está vacío o no tiene encabezados válidos.', ['path' => $filePath]);
            fclose($handle);
            return $uniqueValues;
        }

        $columnIndices = [];
        foreach ($columnNames as $columnName) {
            $index = array_search($columnName, $headers);
            if ($index === false) {
                Log::error("Error: Columna '{$columnName}' no encontrada en el CSV2.", ['headers' => $headers]);
                fclose($handle);
                return $uniqueValues;
            }
            $columnIndices[$columnName] = $index;
        }

        LazyCollection::make(function () use ($handle) {
            while (($row = fgetcsv($handle, 0, ';')) !== false) {
                yield $row;
            }
            fclose($handle);
        })->each(function ($row) use ($columnIndices, &$uniqueValues) {
            foreach ($columnIndices as $columnName => $index) {
                if (isset($row[$index]) && !empty(trim($row[$index]))) {
                    $value = (string) trim($row[$index]);
                    $uniqueValues[$columnName][$value] = true;
                }
            }
        });

        foreach ($uniqueValues as $columnName => &$values) {
            $values = array_keys($values);
        }

        return $uniqueValues;
    }
}
