<?php

namespace App\Imports\ConciliationImport\Traits;

use App\Models\ConciliationResult;
use App\Models\AuditoryFinalReport;
use App\Services\CacheService;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Events\ImportProgressEvent; // Añadido: Importar el evento de progreso
use App\Imports\ConciliationImport\Services\CsvValidationService;
use SplFileObject; // Añadido: Importar SplFileObject

trait ImportHelper
{
    protected float $benchmarkStartTime;
    protected int $benchmarkStartMemory;
    protected int $startRowCount;
    protected int $startQueries;
    protected string $currentBatchId;
    protected int $totalRowsForJobProgress; // Añadido para mantener el total de filas del archivo original para el cálculo de progreso global

    protected function startBenchmark(string $table = 'conciliation_results'): void
    {
        $this->startRowCount = DB::table($table)->count();
        $this->benchmarkStartTime = microtime(true);
        $this->benchmarkStartMemory = memory_get_usage(); // Corregido: memory_usage() a memory_get_usage()
        DB::enableQueryLog();
        $this->startQueries = DB::select("SHOW SESSION STATUS LIKE 'Questions'")[0]->Value;
    }

    protected function endBenchmark(string $table = 'conciliation_results'): void
    {
        $executionTime = microtime(true) - $this->benchmarkStartTime;
        $memoryUsage = round((memory_get_usage() - $this->benchmarkStartMemory) / 1024 / 1024, 2);
        $queriesCount = DB::select("SHOW SESSION STATUS LIKE 'Questions'")[0]->Value - (isset($this->startQueries) ? $this->startQueries : 0) - 1;
        $rowDiff = DB::table($table)->count() - $this->startRowCount;
        $formattedTime = match (true) {
            $executionTime >= 60 => sprintf('%dm %ds', floor($executionTime / 60), $executionTime % 60),
            $executionTime >= 1 => round($executionTime, 2) . 's',
            default => round($executionTime * 1000) . 'ms',
        };
        Log::info(sprintf(
            '⚡ Batch %s | TIME: %s | MEM: %sMB | SQL: %s | ROWS: %s',
            $this->currentBatchId, // Añadido el batch ID aquí
            $formattedTime,
            $memoryUsage,
            number_format($queriesCount),
            number_format($rowDiff)
        ));
    }

    /**
     * Extrae todos los IDs únicos de una columna específica del CSV.
     */
    protected function getUniqueIdsFromCsv(string $filePath, string $columnName): array
    {
        $ids = [];
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            Log::error("Error: No se pudo abrir el archivo CSV para extraer IDs.", ['path' => $filePath]);
            return [];
        }

        $headers = fgetcsv($handle, 0, ';');
        if ($headers && !empty($headers[0])) {
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        }
        $columnIndex = array_search($columnName, $headers);

        if ($columnIndex === false) {
            Log::error("Error: Columna '{$columnName}' no encontrada en el CSV al extraer IDs.", ['headers' => $headers]);
            fclose($handle);
            return [];
        }

        LazyCollection::make(function () use ($handle) {
            while (($row = fgetcsv($handle, 0, ';')) !== false) {
                yield $row;
            }
            fclose($handle);
        })->each(function ($row) use (&$ids, $columnIndex) {
            if (isset($row[$columnIndex]) && !empty(trim($row[$columnIndex]))) {
                $id = (string) trim($row[$columnIndex]);
                $ids[$id] = true;
            }
        });

        Log::info("DEBUG CSV IDs: IDs extraídos del CSV para precarga (primeros 10): " . json_encode(array_slice(array_keys($ids), 0, 10)));
        return array_keys($ids);
    }

    /**
     * Precarga los ID y valor_glosa de AuditoryFinalReport a Redis,
     * pero solo para los IDs presentes en el archivo de importación.
     * Ahora lee de la caché maestra global de Redis, o la precarga si no existe.
     */
    protected function preloadAuditoryGlosaForCsvIds(array $fileIds): void
    {
        $redisMasterKey = 'auditory_glosa_master';
        $cacheService = app(CacheService::class);

        Log::info("DEBUG PRELOAD GLOSA: Iniciando precarga para batch ID: {$this->currentBatchId}. IDs a procesar: " . count($fileIds));
        Log::info("DEBUG PRELOAD GLOSA: Primeros 10 IDs del archivo: " . json_encode(array_slice($fileIds, 0, 10)));

        $cacheService->clearByPrefix("auditory_glosa:{$this->currentBatchId}:");
        Log::info("DEBUG PRELOAD GLOSA: Limpiando claves de 'auditory_glosa' para el batch actual al inicio de la precarga.");

        $preloadStartTime = microtime(true);
        $count = 0;
        $foundIdsInMasterCache = [];
        $idsToLoadFromDb = [];
        $chunkSize = 1000;

        // Paso 1: Identificar qué IDs del archivo ya están en la caché maestra y cuáles faltan
        if (Redis::connection("redis_6380")->exists($redisMasterKey)) {
            Log::info("DEBUG PRELOAD GLOSA: La caché maestra '{$redisMasterKey}' ya existe. Verificando IDs del archivo...");
            foreach (array_chunk($fileIds, $chunkSize) as $idChunk) {
                $idChunk = array_map('strval', $idChunk); // Asegurar que los IDs en el chunk son cadenas
                $glosaValues = Redis::connection("redis_6380")->hmget($redisMasterKey, $idChunk);
                foreach ($idChunk as $index => $id) {
                    if (!is_null($glosaValues[$index])) {
                        $foundIdsInMasterCache[] = $id;
                    } else {
                        $idsToLoadFromDb[] = $id;
                    }
                }
            }
            Log::info(sprintf("DEBUG PRELOAD GLOSA: %d IDs del archivo ya están en caché maestra. %d IDs necesitan ser cargados de DB.", count($foundIdsInMasterCache), count($idsToLoadFromDb)));
        } else {
            Log::info("DEBUG PRELOAD GLOSA: La caché maestra '{$redisMasterKey}' no existe. Todos los IDs del archivo necesitan ser cargados de DB.");
            $idsToLoadFromDb = $fileIds;
        }

        // Paso 2: Cargar los IDs faltantes de la DB a la caché maestra
        if (!empty($idsToLoadFromDb)) {
            Log::info(sprintf("DEBUG PRELOAD GLOSA: Cargando %d IDs de auditory_final_reports desde la base de datos a la caché maestra...", count($idsToLoadFromDb)));
            Log::info("DEBUG PRELOAD GLOSA: Primeros 10 IDs a cargar de DB: " . json_encode(array_slice($idsToLoadFromDb, 0, 10)));
            $dbLoadCount = 0;
            $dbLoadStartTime = microtime(true);
            $dbPipeline = Redis::connection("redis_6380")->pipeline();
            $processedChunks = 0;

            foreach (array_chunk($idsToLoadFromDb, $chunkSize) as $idChunkForDb) {
                $processedChunks++;
                $idChunkForDb = array_map('strval', $idChunkForDb); // Asegurar que los IDs para whereIn son cadenas
                Log::info(sprintf("DEBUG PRELOAD GLOSA DB: Procesando chunk %d con %d IDs. Primeros 5 IDs: %s", $processedChunks, count($idChunkForDb), implode(', ', array_slice($idChunkForDb, 0, 5))));

                $dbReports = AuditoryFinalReport::select('id', 'valor_glosa')
                    ->whereIn('id', $idChunkForDb)
                    ->get(); // Usar get() para procesar el chunk completo

                Log::info(sprintf("DEBUG PRELOAD GLOSA DB: Chunk %d - Recibidos %d reportes de la DB.", $processedChunks, $dbReports->count()));
                Log::info("DEBUG PRELOAD GLOSA DB: Primeros 5 IDs encontrados en DB para este chunk: " . json_encode(array_slice($dbReports->pluck('id')->toArray(), 0, 5)));

                foreach ($dbReports as $report) {
                    $dbPipeline->hset($redisMasterKey, (string) $report->id, (string) $report->valor_glosa);
                    $dbLoadCount++;
                }
            }
            Log::info("DEBUG PRELOAD GLOSA DB: Ejecutando pipeline de Redis para la carga de DB...");
            $dbPipeline->execute();
            Log::info("DEBUG PRELOAD GLOSA DB: Pipeline de Redis ejecutado.");
            Redis::connection("redis_6380")->expire($redisMasterKey, 60 * 60 * 24 * 180); // 6 meses
            $dbLoadEndTime = microtime(true);
            Log::info(sprintf("DEBUG PRELOAD GLOSA DB: Carga de %d IDs desde DB a caché maestra completada en %.2f segundos.", $dbLoadCount, ($dbLoadEndTime - $dbLoadStartTime)));
        } else {
            Log::info("DEBUG PRELOAD GLOSA: No hay IDs del archivo que necesiten ser cargados de la base de datos a la caché maestra.");
        }

        // Paso 3: Poblar la caché específica del batch desde la caché maestra (ahora actualizada)
        $finalFoundIdsForBatch = [];
        $pipeline = Redis::connection("redis_6380")->pipeline();
        foreach (array_chunk($fileIds, $chunkSize) as $idChunk) {
            $idChunk = array_map('strval', $idChunk); // Asegurar que los IDs en el chunk son cadenas
            $glosaValues = Redis::connection("redis_6380")->hmget($redisMasterKey, $idChunk);
            foreach ($idChunk as $index => $id) {
                if (!is_null($glosaValues[$index])) {
                    $redisKey = "auditory_glosa:{$this->currentBatchId}:{$id}";
                    $pipeline->set($redisKey, (string) $glosaValues[$index]);
                    $finalFoundIdsForBatch[] = $id;
                    $count++;
                }
            }
        }
        $pipeline->execute();

        $preloadEndTime = microtime(true);
        Log::info(sprintf("DEBUG PRELOAD GLOSA: Resumen de precarga de glosa (para batch): %d registros en %.2f segundos.", $count, ($preloadEndTime - $preloadStartTime)));

        $notFoundIds = array_diff($fileIds, $finalFoundIdsForBatch);
        if (!empty($notFoundIds)) {
            Log::warning(sprintf("ATENCIÓN PRELOAD GLOSA: %d IDs del archivo no se encontraron en la caché maestra '{$redisMasterKey}' y no fueron precargados para este batch. Primeros 10 IDs no encontrados:", count($notFoundIds)));
            Log::warning(json_encode(array_slice($notFoundIds, 0, 10)));
        } else {
            Log::info("DEBUG PRELOAD GLOSA: Todos los IDs del archivo se encontraron en la caché maestra '{$redisMasterKey}' y fueron precargados para este batch.");
        }
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

        Log::info("DEBUG PRELOAD FACTURA: Iniciando precarga para batch ID: {$this->currentBatchId}. IDs de factura a procesar: " . count($fileFacturaIds));
        Log::info("DEBUG PRELOAD FACTURA: Primeros 10 IDs de factura del archivo: " . json_encode(array_slice($fileFacturaIds, 0, 10)));

        $cacheService->clearByPrefix("db_factura_total_glosa_counts:{$this->currentBatchId}");
        Log::info("DEBUG PRELOAD FACTURA: Limpiando claves de 'db_factura_total_glosa_counts' para el batch actual al inicio de la precarga.");

        $preloadStartTime = microtime(true);
        $count = 0;
        $foundFacturaIdsInMasterCache = [];
        $facturaIdsToLoadFromDb = [];
        $chunkSize = 1000;

        // Paso 1: Identificar qué IDs de factura del archivo ya están en la caché maestra y cuáles faltan
        if (Redis::connection("redis_6380")->exists($redisMasterKey)) {
            Log::info("DEBUG PRELOAD FACTURA: La caché maestra '{$redisMasterKey}' ya existe. Verificando IDs de factura del archivo...");
            foreach (array_chunk($fileFacturaIds, $chunkSize) as $facturaIdChunk) {
                $facturaIdChunk = array_map('strval', $facturaIdChunk); // Asegurar que los IDs en el chunk son cadenas
                $countsValues = Redis::connection("redis_6380")->hmget($redisMasterKey, $facturaIdChunk);
                foreach ($facturaIdChunk as $index => $facturaId) {
                    if (!is_null($countsValues[$index])) {
                        $foundFacturaIdsInMasterCache[] = $facturaId;
                    } else {
                        $facturaIdsToLoadFromDb[] = $facturaId;
                    }
                }
            }
            Log::info(sprintf("DEBUG PRELOAD FACTURA: %d IDs de factura del archivo ya están en caché maestra. %d IDs necesitan ser cargados de DB.", count($foundFacturaIdsInMasterCache), count($facturaIdsToLoadFromDb)));
        } else {
            Log::info("DEBUG PRELOAD FACTURA: La caché maestra '{$redisMasterKey}' no existe. Todos los IDs de factura del archivo necesitan ser cargados de DB.");
            $facturaIdsToLoadFromDb = $fileFacturaIds;
        }

        // Paso 2: Cargar los IDs de factura faltantes de la DB a la caché maestra
        if (!empty($facturaIdsToLoadFromDb)) {
            Log::info(sprintf("DEBUG PRELOAD FACTURA: Cargando %d IDs de factura (con glosa > 0) desde la base de datos a la caché maestra...", count($facturaIdsToLoadFromDb)));
            Log::info("DEBUG PRELOAD FACTURA: Primeros 10 IDs de factura a cargar de DB: " . json_encode(array_slice($facturaIdsToLoadFromDb, 0, 10)));
            $dbLoadCount = 0;
            $dbLoadStartTime = microtime(true);
            $dbPipeline = Redis::connection("redis_6380")->pipeline();

            foreach (array_chunk($facturaIdsToLoadFromDb, $chunkSize) as $facturaIdChunkForDb) {
                $facturaIdChunkForDb = array_map('strval', $facturaIdChunkForDb); // Asegurar que los IDs para whereIn son cadenas
                $dbResults = AuditoryFinalReport::select('factura_id', DB::raw('COUNT(*) as total_count'))
                    ->whereIn('factura_id', $facturaIdChunkForDb)
                    ->where('valor_glosa', '>', 0)
                    ->groupBy('factura_id')
                    ->orderBy('factura_id')
                    ->get(); // Usar get() para procesar el chunk completo

                Log::info(sprintf("DEBUG PRELOAD FACTURA DB: Recibidos %d resultados de la DB para este chunk.", $dbResults->count()));
                Log::info("DEBUG PRELOAD FACTURA DB: Primeros 5 IDs de factura encontrados en DB para este chunk: " . json_encode(array_slice($dbResults->pluck('factura_id')->toArray(), 0, 5)));

                foreach ($dbResults as $result) {
                    $dbPipeline->hset($redisMasterKey, (string) $result->factura_id, (string) $result->total_count);
                    $dbLoadCount++;
                }
            }
            $dbPipeline->execute();
            Redis::connection("redis_6380")->expire($redisMasterKey, 60 * 60 * 24 * 180); // 6 meses
            $dbLoadEndTime = microtime(true);
            Log::info(sprintf("DEBUG PRELOAD FACTURA DB: Carga de %d IDs de factura desde DB a caché maestra completada en %.2f segundos.", $dbLoadCount, ($dbLoadEndTime - $dbLoadStartTime)));
        } else {
            Log::info("DEBUG PRELOAD FACTURA: No hay IDs de factura del archivo que necesiten ser cargados de la base de datos a la caché maestra.");
        }

        // Paso 3: Poblar la caché específica del batch desde la caché maestra (ahora actualizada)
        $finalFoundFacturaIdsForBatch = [];
        $pipeline = Redis::connection("redis_6380")->pipeline();
        $redisHashKey = "db_factura_total_glosa_counts:{$this->currentBatchId}";

        foreach (array_chunk($fileFacturaIds, $chunkSize) as $facturaIdChunk) {
            $facturaIdChunk = array_map('strval', $facturaIdChunk); // Asegurar que los IDs en el chunk son cadenas
            $countsValues = Redis::connection("redis_6380")->hmget($redisMasterKey, $facturaIdChunk);
            foreach ($facturaIdChunk as $index => $facturaId) {
                if (!is_null($countsValues[$index])) {
                    $pipeline->hset($redisHashKey, (string) $facturaId, (string) $countsValues[$index]);
                    $finalFoundFacturaIdsForBatch[] = $facturaId;
                    $count++;
                }
            }
        }
        $pipeline->execute();

        $preloadEndTime = microtime(true);
        Log::info(sprintf("DEBUG PRELOAD FACTURA: Resumen de precarga de conteos de factura (para batch): %d registros en %.2f segundos.", $count, ($preloadEndTime - $preloadStartTime)));

        $notFoundFacturaIds = array_diff($fileFacturaIds, $finalFoundFacturaIdsForBatch);
        if (!empty($notFoundFacturaIds)) { // CORREGIDO: Usar $notFoundFacturaIds
            Log::warning(sprintf("ATENCIÓN PRELOAD FACTURA: %d IDs de factura del archivo no se encontraron en la caché maestra '{$redisMasterKey}' y no fueron precargados para este batch. Primeros 10 IDs no encontrados:", count($notFoundFacturaIds))); // CORREGIDO: Usar $notFoundFacturaIds
            Log::warning(json_encode(array_slice($notFoundFacturaIds, 0, 10))); // CORREGIDO: Usar $notFoundFacturaIds
        } else {
            Log::info("DEBUG PRELOAD FACTURA: Todos los IDs de factura del archivo se encontraron en la caché maestra '{$redisMasterKey}' y fueron precargados para este batch.");
        }
    }

    /**
     * Realiza la validación de "Facturas Completas".
     * Compara el número de registros de una factura en el archivo con el número de glosas > 0 en la DB.
     * @param CsvValidationService $validationService Instancia del servicio de validación.
     */
    protected function performFacturaCompletaValidation(CsvValidationService $validationService): void
    {
        Log::info("Iniciando performFacturaCompletaValidation para batch ID: {$this->currentBatchId}");

        $uniqueFacturaIdsFromCsv = Redis::connection("redis_6380")->smembers("csv_unique_factura_ids:{$this->currentBatchId}");
        $totalFacturaIds = count($uniqueFacturaIdsFromCsv);
        $processedFacturaIds = 0;
        $dispatchInterval = max(1, floor($totalFacturaIds / 100)); // Despachar al menos 100 veces

        foreach ($uniqueFacturaIdsFromCsv as $facturaId) {
            $processedFacturaIds++;

            // Obtener el conteo de filas para esta factura en el archivo de importación
            $fileFacturaCount = (int) Redis::connection("redis_6380")->hget("csv_factura_total_counts:{$this->currentBatchId}", $facturaId);

            // Obtener el conteo de glosas > 0 para esta factura en la base de datos (precargado)
            $dbGlosaCount = (int) Redis::connection("redis_6380")->hget("db_factura_total_glosa_counts:{$this->currentBatchId}", $facturaId);

            Log::debug(sprintf(
                "DEBUG FACTURA COMPLETA: Factura ID '%s' - Archivo: %d, DB Glosa: %d",
                $facturaId,
                $fileFacturaCount,
                $dbGlosaCount
            ));

            if ($fileFacturaCount !== $dbGlosaCount) {
                // Recuperar los números de fila asociados a esta factura para el mensaje de error
                $rowNumbersJson = Redis::connection("redis_6380")->lrange("csv_factura_rows:{$this->currentBatchId}:{$facturaId}", 0, -1);
                $rowNumbers = array_map('intval', $rowNumbersJson);
                $firstRow = !empty($rowNumbers) ? min($rowNumbers) : 0; // Usar la primera fila para el error

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
                Log::warning("ERROR FACTURA COMPLETA: " . $errorMessage);
            }

            // Despachar evento de progreso periódicamente
            if ($processedFacturaIds % $dispatchInterval === 0 || $processedFacturaIds === $totalFacturaIds) {
                if (method_exists($this, 'dispatchProgressEvent')) {
                    logMessage("hola juan");
                    $this->dispatchProgressEvent(
                        $this->totalRowsForJobProgress, // Mantiene el progreso principal en 100% (de validación de filas)
                        'Validando facturas completas',
                        'active',
                        sprintf('%d/%d facturas validadas', $processedFacturaIds, $totalFacturaIds) // Detalle en currentStudent
                    );
                }
            }
        }
        Log::info("Finalizado performFacturaCompletaValidation para batch ID: {$this->currentBatchId}");
    }


    /**
     * Retrieves errors from Redis for the current batch and inserts them into the database.
     */
    protected function storeErrorsFromRedis(): void
    {
        $errorKey = "import_errors:{$this->currentBatchId}";
        Log::info("Attempting to retrieve errors from Redis key: {$errorKey}");

        $rawErrors = Redis::connection("redis_6380")->lrange($errorKey, 0, -1);

        if (empty($rawErrors)) {
            Log::info("No errors found in Redis for batch ID: {$this->currentBatchId}");
            return;
        }

        $errorsToInsert = [];
        foreach ($rawErrors as $errorJson) {
            $decodedError = json_decode($errorJson, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $errorsToInsert[] = $decodedError;
            } else {
                Log::error("Failed to decode JSON error from Redis: " . json_last_error_msg(), ['json' => $errorJson]);
            }
        }

        if (!empty($errorsToInsert)) {
            $chunkSize = 500;
            $totalErrors = count($errorsToInsert);
            Log::info(sprintf('Found %d errors to insert into DB for batch ID: %s. Inserting in chunks of %d.', $totalErrors, $this->currentBatchId, $chunkSize));

            foreach (array_chunk($errorsToInsert, $chunkSize) as $chunk) {
                DB::transaction(function () use ($chunk, $totalErrors) {
                    try {
                        DB::table('process_batche_errors')->insert($chunk);
                        Log::info(sprintf('Successfully inserted a chunk of %d errors into process_batche_errors table.', count($chunk)));
                    } catch (\Exception $e) {
                        Log::error('Failed to bulk insert errors into process_batche_errors: ' . $e->getMessage());
                        Log::error('Database insertion failed for errors chunk:', [
                            'exception' => $e->getMessage(),
                            'code' => $e->getCode(),
                            'trace' => $e->getTraceAsString(),
                            'errors_attempted_to_insert' => $chunk
                        ]);
                        throw $e;
                    }
                    Log::info(sprintf('Stored %d validation errors to process_batche_errors table.', $totalErrors));
                    Log::info('Finished inserting all errors into process_batche_errors table.');
                });
            }

            $persistedErrorsCount = DB::table('process_batche_errors')
                ->where('batch_id', $this->currentBatchId)
                ->count();
            Log::info(sprintf('DEBUG DB: %d errores encontrados en la DB para batch ID: %s inmediatamente después de la inserción.', $persistedErrorsCount, $this->currentBatchId));
        } else {
            Log::info("No valid errors to insert after decoding for batch ID: {$this->currentBatchId}");
        }
        Redis::connection("redis_6380")->del($errorKey);
    }

    /**
     * Realiza la importación concurrente de datos desde un archivo CSV.
     * @param string $filePath Ruta completa al archivo CSV.
     */
    protected function import11ConcurrentCsv(string $filePath): void
    {
        Log::info("iniciando import11Concurrent desde CSV");
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
        Redis::connection("redis_6380")->set("batch:{$batchId}:imported_rows_count", 0);
        Redis::connection("redis_6380")->expire("batch:{$batchId}:imported_rows_count", 3600 * 24); // Expiración de 24 horas

        $dispatchInterval = max(1, floor($totalRows / 100)); // Despachar al menos 100 veces

        for ($i = 0; $i < $numberOfProcesses; $i++) {
            // CORREGIDO: Eliminado $this de la cláusula use. $this ya es accesible y su inclusión explícita causa el error de serialización.
            $tasks[] = function () use ($filePath, $i, $numberOfProcesses, $now, $dispatchInterval, $totalRows, $batchId, $totalRowsForEvent) {
                DB::reconnect();
                $handle = fopen($filePath, 'r');
                fgets($handle); // Skip header
                $currentLine = 0;
                $customers = [];

                while (($line = fgets($handle)) !== false) {
                    if ($currentLine++ % $numberOfProcesses !== $i) {
                        continue;
                    }
                    $row = str_getcsv($line, ';');
                    $customers[] = [
                        'id' => (string) Str::uuid(),
                        'auditory_final_report_id' => (string) trim($row[0]),
                        'response_status' => (string) trim($row[29]),
                        'autorization_number' => (string) trim($row[30]),
                        'accepted_value_ips' => (float)str_replace(',', '.', trim($row[31])),
                        'accepted_value_eps' => (float)str_replace(',', '.', trim($row[32])),
                        'eps_ratified_value' => (float)str_replace(',', '.', trim($row[33])),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    if (count($customers) === 1000) {
                        DB::table('conciliation_results')->insert($customers);
                        $customers = [];
                    }

                    // Incrementar contador global de Redis para filas importadas
                    $currentImportedCount = Redis::connection("redis_6380")->incr("batch:{$batchId}:imported_rows_count");

                    // NOTA: Se ha eliminado la llamada a dispatchProgressEvent aquí para evitar problemas de serialización.
                    // El progreso de importación se reflejará principalmente a través del contador de Redis
                    // y el evento final de "Importación completada".
                }
                if (! empty($customers)) {
                    DB::table('conciliation_results')->insert($customers);
                }
                fclose($handle);
                return true;
            };
        }
        Concurrency::run($tasks);

        // Asegurar que el evento final de importación se despacha después de que todas las tareas concurrentes completen
        $finalImportedCount = (string) Redis::connection("redis_6380")->get("batch:{$batchId}:imported_rows_count");
        // CORREGIDO: Llamar a dispatchProgressEvent del trait. Esto es seguro aquí porque no está dentro de un closure serializado.
        $this->dispatchProgressEvent(
            $totalRowsForEvent, // processedRecords para el evento (progreso principal es 100% de validación)
            'Importación completada',
            'completed',
            sprintf('%s/%d registros importados', $finalImportedCount, $totalRows) // Detalle en currentStudent
        );

        // Limpiar el contador de filas importadas de Redis
        Redis::connection("redis_6380")->del("batch:{$batchId}:imported_rows_count");
    }
}
