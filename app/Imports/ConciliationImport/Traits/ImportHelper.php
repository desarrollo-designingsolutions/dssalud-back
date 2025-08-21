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
    protected function import11ConcurrentCsv(string $filePath, string $reconciliation_group_id): void
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

        $batchId = $this->currentBatchId;
        $totalRowsForEvent = $this->totalRowsForJobProgress; // Total de filas del archivo para el progreso principal

        // Inicializar contador de Redis para filas importadas
        Redis::connection('redis_6380')->set("batch:{$batchId}:imported_rows_count", 0);
        Redis::connection('redis_6380')->expire("batch:{$batchId}:imported_rows_count", 3600 * 24); // Expiración de 24 horas

        for ($i = 0; $i < $numberOfProcesses; $i++) {
            // CORREGIDO: Eliminado $this de la cláusula use. $this ya es accesible y su inclusión explícita causa el error de serialización.
            $tasks[] = function () use ($filePath, $i, $numberOfProcesses, $now, $batchId, $reconciliation_group_id) {
                DB::reconnect();
                $handle = fopen($filePath, 'r');
                fgets($handle); // Skip header
                $currentLine = 0;
                $dataToSave = [];

                while (($line = fgets($handle)) !== false) {
                    if ($currentLine++ % $numberOfProcesses !== $i) {
                        continue;
                    }
                    $row = str_getcsv($line, ';');

                    // Aplica trim y ensureUtf8 a todo el arreglo $row
                    $row = array_map('trim', $row); // Elimina espacios en blanco de cada elemento
                    $row = ensureUtf8($row); // Convierte todas las cadenas a UTF-8


                    $dataToSave[] = [
                        'id' => (string) Str::uuid(),
                        'auditory_final_report_id' => (string) $row[0],
                        'invoice_audit_id' => (string) $row[1],
                        'reconciliation_group_id' => $reconciliation_group_id,
                        'response_status' => (string) $row[29],
                        'autorization_number' => (string) $row[30],
                        'accepted_value_ips' => (float) str_replace(',', '.', $row[32]),
                        'accepted_value_eps' => (float) str_replace(',', '.', $row[33]),
                        'eps_ratified_value' => (float) str_replace(',', '.', $row[34]),
                        'eps_ratified_value' => (float) str_replace(',', '.', $row[34]),
                        'observation' =>  $row[35],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    // Recolectar datos para actualizar conciliation_invoices
                    $invoicesToUpdate[] = [
                        'invoice_audit_id' => (string) $row[1],
                        'status' => "CONCILIATION_INVOICE_EST_002", //estado finalizado
                    ];

                    if (count($dataToSave) === 1000) {
                        // Insertar en conciliation_results
                        DB::table('conciliation_results')->insert($dataToSave);

                        // Actualizar conciliation_invoices en lotes
                        DB::transaction(function () use ($invoicesToUpdate) {
                            $ids = array_column($invoicesToUpdate, 'invoice_audit_id');
                            if (!empty($ids)) {
                                DB::table('conciliation_invoices')
                                    ->whereIn('invoice_audit_id', $ids)
                                    ->update(['status' => 'CONCILIATION_INVOICE_EST_002']);
                            }
                        });
                        Log::info("Actualizados " . count($invoicesToUpdate) . " registros en conciliation_invoices");

                        $dataToSave = [];
                        $invoicesToUpdate = [];
                    }

                    // Incrementar contador global de Redis para filas importadas
                    $currentImportedCount = Redis::connection('redis_6380')->incr("batch:{$batchId}:imported_rows_count");
                }

                // Insertar y actualizar registros restantes
                if (!empty($dataToSave)) {
                    DB::table('conciliation_results')->insert($dataToSave);
                    DB::transaction(function () use ($invoicesToUpdate) {
                        $ids = array_column($invoicesToUpdate, 'invoice_audit_id');
                        if (!empty($ids)) {
                            DB::table('conciliation_invoices')
                                ->whereIn('invoice_audit_id', $ids)
                                ->update(['status' => 'CONCILIATION_INVOICE_EST_002']);
                        }
                    });
                    Log::info("Actualizados " . count($invoicesToUpdate) . " registros en conciliation_invoices");
                }

                fclose($handle);

                return true;
            };
        }
        Concurrency::run($tasks);

        // Asegurar que el evento final de importación se despacha después de que todas las tareas concurrentes completen
        $finalImportedCount = (string) Redis::connection('redis_6380')->get("batch:{$batchId}:imported_rows_count");
        // CORREGIDO: Llamar a dispatchProgressEvent del trait. Esto es seguro aquí porque no está dentro de un closure serializado.
        $this->dispatchProgressEvent(
            $totalRowsForEvent, // processedRecords para el evento (progreso principal es 100% de validación)
            'Importación completada',
            'completed',
            sprintf('%s/%d registros importados', $finalImportedCount, $totalRows) // Detalle en currentStudent
        );

        // Limpiar el contador de filas importadas de Redis
        Redis::connection('redis_6380')->del("batch:{$batchId}:imported_rows_count");
    }


    public function getUniqueValuesFromCsv(string $filePath, $columnNames, $dispatchInterval = 100): array
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
                Log::error("Error: Columna '{$columnName}' no encontrada en el CSV.", ['headers' => $headers]);
                fclose($handle);
                return $uniqueValues;
            }
            $columnIndices[$columnName] = $index;
        }

        $processedRows = 0;
        $totalRows = $this->countCsvRows($filePath); // Necesitarás implementar esta función

        LazyCollection::make(function () use ($handle) {
            while (($row = fgetcsv($handle, 0, ';')) !== false) {
                yield $row;
            }
            fclose($handle);
        })->each(function ($row) use ($columnIndices, &$uniqueValues, &$processedRows, $totalRows, $dispatchInterval) {
            foreach ($columnIndices as $columnName => $index) {
                if (isset($row[$index])) {
                    $value = trim($row[$index]);
                    if ($value !== '') {
                        $uniqueValues[$columnName][$value] = true;
                    }
                }
            }

            $processedRows++;

            // Despachar evento de progreso periódicamente
            if ($processedRows % $dispatchInterval === 0 || $processedRows === $totalRows) {
                if (method_exists($this, 'dispatchProgressEvent')) {
                    $this->dispatchProgressEvent(
                        $totalRows,
                        'Procesando archivo CSV',
                        'active',
                        sprintf('%d/%d filas procesadas', $processedRows, $totalRows)
                    );
                }
            }
        });

        foreach ($uniqueValues as $columnName => &$values) {
            $values = array_keys($values);
        }

        return $uniqueValues;
    }

    /**
     * Cuenta el número total de filas en el archivo CSV (sin contar el encabezado)
     */
    protected function countCsvRows(string $filePath): int
    {
        $file = new \SplFileObject($filePath, 'r');
        $file->seek(PHP_INT_MAX); // Busca el final del archivo
        $totalRows = $file->key();
        $file = null; // Cierra el archivo

        // Restamos 1 para excluir el encabezado
        return max(0, $totalRows - 1);
    }
}
