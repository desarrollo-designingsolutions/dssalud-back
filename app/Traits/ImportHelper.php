<?php

namespace App\Traits;

use App\Models\ConciliationResult;
use App\Models\AuditoryFinalReport;
use App\Services\CsvValidationService;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use PDO;
use PDOStatement;
use App\Services\CacheService; // Importar tu CacheService

trait ImportHelper
{
    protected float $benchmarkStartTime;
    protected int $benchmarkStartMemory;
    protected int $startRowCount;
    protected int $startQueries;
    protected string $currentBatchId;

    protected function startBenchmark(string $table = 'conciliation_results'): void
    {
        $this->startRowCount = DB::table($table)->count();
        $this->benchmarkStartTime = microtime(true);
        $this->benchmarkStartMemory = memory_get_usage();
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
            '⚡ TIME: %s | MEM: %sMB | SQL: %s | ROWS: %s',
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
                $ids[trim($row[$columnIndex])] = true;
            }
        });

        return array_keys($ids);
    }

    /**
     * Precarga los ID y valor_glosa de AuditoryFinalReport a Redis,
     * pero solo para los IDs presentes en el CSV.
     * Ahora lee de la caché maestra global de Redis, o la precarga si no existe.
     */
    protected function preloadAuditoryGlosaForCsvIds(array $csvIds): void
    {
        $redisMasterKey = 'auditory_glosa_master';
        $cacheService = app(CacheService::class); // Resolver CacheService desde el contenedor

        // Limpiar las claves específicas del batch antes de precargar usando CacheService
        Log::info("DEBUG PRELOAD: Limpiando claves de 'auditory_glosa' para el batch actual al inicio de la precarga.");
        $cacheService->clearByPrefix("auditory_glosa:{$this->currentBatchId}:");

        $preloadStartTime = microtime(true);
        $count = 0;
        $foundIdsInMasterCache = []; // IDs que ya están en la caché maestra
        $idsToLoadFromDb = []; // IDs del CSV que no están en la caché maestra y necesitan ser cargados de la DB
        $chunkSize = 1000;

        // Paso 1: Identificar qué IDs del CSV ya están en la caché maestra y cuáles faltan
        // Usar la variable $masterCacheExists directamente
        if (Redis::exists($redisMasterKey)) { // Corregido: usar Redis::exists() directamente aquí
            Log::info("La caché maestra '{$redisMasterKey}' ya existe. Verificando IDs del CSV...");
            foreach (array_chunk($csvIds, $chunkSize) as $idChunk) {
                $glosaValues = Redis::hmget($redisMasterKey, $idChunk);
                foreach ($idChunk as $index => $id) {
                    if (!is_null($glosaValues[$index])) {
                        $foundIdsInMasterCache[] = $id;
                    } else {
                        $idsToLoadFromDb[] = $id;
                    }
                }
            }
            Log::info(sprintf("%d IDs del CSV ya están en caché maestra. %d IDs necesitan ser cargados de DB.", count($foundIdsInMasterCache), count($idsToLoadFromDb)));
        } else {
            Log::info("La caché maestra '{$redisMasterKey}' no existe. Todos los IDs del CSV necesitan ser cargados de DB.");
            $idsToLoadFromDb = $csvIds;
        }

        // Paso 2: Cargar los IDs faltantes de la DB a la caché maestra
        if (!empty($idsToLoadFromDb)) {
            Log::info(sprintf("Cargando %d IDs de auditory_final_reports desde la base de datos a la caché maestra...", count($idsToLoadFromDb)));
            $dbLoadCount = 0;
            $dbLoadStartTime = microtime(true);
            $dbPipeline = Redis::pipeline();

            foreach (array_chunk($idsToLoadFromDb, $chunkSize) as $idChunkForDb) {
                AuditoryFinalReport::select('id', 'valor_glosa')
                    ->whereIn('id', $idChunkForDb)
                    ->orderBy('id')
                    ->chunk(1000, function ($reports) use (&$dbLoadCount, $dbPipeline, $redisMasterKey) {
                        foreach ($reports as $report) {
                            $dbPipeline->hset($redisMasterKey, $report->id, (string) $report->valor_glosa);
                            $dbLoadCount++;
                        }
                    });
            }
            $dbPipeline->execute();
            // Establecer expiración para la caché maestra (o refrescarla si ya existía)
            Redis::expire($redisMasterKey, 60 * 60 * 24 * 180); // 6 meses
            $dbLoadEndTime = microtime(true);
            Log::info(sprintf("Carga de %d IDs desde DB a caché maestra completada en %.2f segundos.", $dbLoadCount, ($dbLoadEndTime - $dbLoadStartTime)));
        } else {
            Log::info("No hay IDs del CSV que necesiten ser cargados de la base de datos a la caché maestra.");
        }

        // Paso 3: Poblar la caché específica del batch desde la caché maestra (ahora actualizada)
        $finalFoundIdsForBatch = [];
        $pipeline = Redis::pipeline();
        foreach (array_chunk($csvIds, $chunkSize) as $idChunk) {
            $glosaValues = Redis::hmget($redisMasterKey, $idChunk);
            foreach ($idChunk as $index => $id) {
                if (!is_null($glosaValues[$index])) {
                    $redisKey = "auditory_glosa:{$this->currentBatchId}:{$id}";
                    $pipeline->set($redisKey, (string) $glosaValues[$index]);
                    $finalFoundIdsForBatch[] = $id;
                    $count++;

                    // DEBUG: Verificar la creación de una clave de glosa específica del batch
                    if ($count === 1) { // Solo para la primera clave para evitar logs excesivos
                        $cachePrefix = config('database.redis.options.prefix', '');
                        $prefixedKey = $cachePrefix . $redisKey;
                        Log::info("DEBUG PRELOAD: Clave de glosa de batch creada: {$prefixedKey}. Valor: " . Redis::get($redisKey));
                    }
                }
            }
        }
        $pipeline->execute();

        $preloadEndTime = microtime(true);
        Log::info(sprintf("Resumen de precarga de glosa (para batch): %d registros en %.2f segundos.", $count, ($preloadEndTime - $preloadStartTime)));

        // Log para depuración: IDs del CSV que no se encontraron en la caché maestra (ni existían ni se pudieron cargar de DB)
        $notFoundIds = array_diff($csvIds, $finalFoundIdsForBatch);
        if (!empty($notFoundIds)) {
            Log::warning(sprintf("ATENCIÓN: %d IDs del CSV no se encontraron en la caché maestra '{$redisMasterKey}' y no fueron precargados para este batch. Primeros 10 IDs no encontrados:", count($notFoundIds)));
            Log::warning(array_slice($notFoundIds, 0, 10));
        } else {
            Log::info("Todos los IDs del CSV se encontraron en la caché maestra '{$redisMasterKey}' y fueron precargados para este batch.");
        }
    }

    /**
     * Precarga los conteos de FACTURA_ID de AuditoryFinalReport (donde valor_glosa > 0) a Redis.
     * Almacena conteos totales de FACTURA_ID.
     * Ahora lee de la caché maestra global de Redis, y la precarga incrementalmente si faltan IDs.
     */
    protected function preloadDbFacturaGlosaCounts(array $csvFacturaIds): void
    {
        $redisMasterKey = 'db_factura_glosa_counts_master';
        $cacheService = app(CacheService::class); // Resolver CacheService desde el contenedor

        // Limpiar las claves específicas del batch antes de precargar usando CacheService
        Log::info("DEBUG PRELOAD: Limpiando claves de 'db_factura_total_glosa_counts' para el batch actual al inicio de la precarga.");
        $cacheService->clearByPrefix("db_factura_total_glosa_counts:{$this->currentBatchId}");

        $preloadStartTime = microtime(true);
        $count = 0;
        $foundFacturaIdsInMasterCache = []; // IDs de factura que ya están en la caché maestra
        $facturaIdsToLoadFromDb = []; // IDs de factura del CSV que no están en la caché maestra y necesitan ser cargados de la DB
        $chunkSize = 1000;

        // Paso 1: Identificar qué IDs de factura del CSV ya están en la caché maestra y cuáles faltan
        if (Redis::exists($redisMasterKey)) { // Corregido: usar Redis::exists() directamente aquí
            Log::info("La caché maestra '{$redisMasterKey}' ya existe. Verificando IDs de factura del CSV...");
            foreach (array_chunk($csvFacturaIds, $chunkSize) as $facturaIdChunk) {
                $countsValues = Redis::hmget($redisMasterKey, $facturaIdChunk);
                foreach ($facturaIdChunk as $index => $facturaId) {
                    if (!is_null($countsValues[$index])) {
                        $foundFacturaIdsInMasterCache[] = $facturaId;
                    } else {
                        $facturaIdsToLoadFromDb[] = $facturaId;
                    }
                }
            }
            Log::info(sprintf("%d IDs de factura del CSV ya están en caché maestra. %d IDs necesitan ser cargados de DB.", count($foundFacturaIdsInMasterCache), count($facturaIdsToLoadFromDb)));
        } else {
            Log::info("La caché maestra '{$redisMasterKey}' no existe. Todos los IDs de factura del CSV necesitan ser cargados de DB.");
            $facturaIdsToLoadFromDb = $csvFacturaIds;
        }

        // Paso 2: Cargar los IDs de factura faltantes de la DB a la caché maestra
        if (!empty($facturaIdsToLoadFromDb)) {
            Log::info(sprintf("Cargando %d IDs de factura (con glosa > 0) desde la base de datos a la caché maestra...", count($facturaIdsToLoadFromDb)));
            $dbLoadCount = 0;
            $dbLoadStartTime = microtime(true);
            $dbPipeline = Redis::pipeline();

            foreach (array_chunk($facturaIdsToLoadFromDb, $chunkSize) as $facturaIdChunkForDb) {
                AuditoryFinalReport::select('factura_id', DB::raw('COUNT(*) as total_count'))
                    ->whereIn('factura_id', $facturaIdChunkForDb)
                    ->where('valor_glosa', '>', 0)
                    ->groupBy('factura_id')
                    ->orderBy('factura_id')
                    ->chunk(1000, function ($results) use (&$dbLoadCount, $dbPipeline, $redisMasterKey) {
                        foreach ($results as $result) {
                            $dbPipeline->hset($redisMasterKey, $result->factura_id, (string) $result->total_count);
                            $dbLoadCount++;
                        }
                    });
            }
            $dbPipeline->execute();
            // Establecer expiración para la caché maestra (o refrescarla si ya existía)
            Redis::expire($redisMasterKey, 60 * 60 * 24 * 180); // 6 meses
            $dbLoadEndTime = microtime(true);
            Log::info(sprintf("Carga de %d IDs de factura desde DB a caché maestra completada en %.2f segundos.", $dbLoadCount, ($dbLoadEndTime - $dbLoadStartTime)));
        } else {
            Log::info("No hay IDs de factura del CSV que necesiten ser cargados de la base de datos a la caché maestra.");
        }

        // Paso 3: Poblar la caché específica del batch desde la caché maestra (ahora actualizada)
        $finalFoundFacturaIdsForBatch = [];
        $pipeline = Redis::pipeline();
        $redisHashKey = "db_factura_total_glosa_counts:{$this->currentBatchId}"; // Clave hash específica del batch

        foreach (array_chunk($csvFacturaIds, $chunkSize) as $facturaIdChunk) {
            $countsValues = Redis::hmget($redisMasterKey, $facturaIdChunk);
            foreach ($facturaIdChunk as $index => $facturaId) {
                if (!is_null($countsValues[$index])) {
                    $pipeline->hset($redisHashKey, $facturaId, (string) $countsValues[$index]);
                    $finalFoundFacturaIdsForBatch[] = $facturaId;
                    $count++;

                    // DEBUG: Verificar la creación de una clave de conteo de factura específica del batch
                    if ($count === 1) { // Solo para la primera clave para evitar logs excesivos
                        $cachePrefix = config('database.redis.options.prefix', '');
                        $prefixedKey = $cachePrefix . $redisHashKey;
                        Log::info("DEBUG PRELOAD: Clave de conteo de factura de batch creada: {$prefixedKey}. Valor: " . Redis::hget($redisHashKey, $facturaId));
                    }
                }
            }
        }
        $pipeline->execute();

        $preloadEndTime = microtime(true);
        Log::info(sprintf("Resumen de precarga de conteos de factura (para batch): %d registros en %.2f segundos.", $count, ($preloadEndTime - $preloadStartTime)));

        // Log para depuración: IDs de factura del CSV que no se encontraron en la caché maestra (ni existían ni se pudieron cargar de DB)
        $notFoundFacturaIds = array_diff($csvFacturaIds, $finalFoundFacturaIdsForBatch);
        if (!empty($notFoundFacturaIds)) {
            Log::warning(sprintf("ATENCIÓN: %d IDs de factura del CSV no se encontraron en la caché maestra '{$redisMasterKey}' y no fueron precargados para este batch. Primeros 10 IDs no encontrados:", count($notFoundFacturaIds)));
            Log::warning(array_slice($notFoundFacturaIds, 0, 10));
        } else {
            Log::info("Todos los IDs de factura del CSV se encontraron en la caché maestra '{$redisMasterKey}' y fueron precargados para este batch.");
        }
    }

    /**
     * Realiza la validación de "Facturas Completas".
     * Compara los conteos totales de FACTURA_ID entre el CSV y la DB (con glosa > 0).
     */
    protected function performFacturaCompletaValidation(CsvValidationService $validationService): void
    {
        $uniqueCsvFacturaIds = Redis::smembers("csv_unique_factura_ids:{$this->currentBatchId}");

        // Obtener todos los conteos de factura del CSV y de la DB
        $csvTotalCounts = Redis::hgetall("csv_factura_total_counts:{$this->currentBatchId}");
        $dbGlosaTotalCounts = Redis::hgetall("db_factura_total_glosa_counts:{$this->currentBatchId}");

        foreach ($uniqueCsvFacturaIds as $facturaId) {
            $csvCount = (int) ($csvTotalCounts[$facturaId] ?? 0);
            $dbCount = (int) ($dbGlosaTotalCounts[$facturaId] ?? 0);

            // Si los conteos no coinciden, o si la factura existe en CSV pero no en DB con glosa > 0
            if ($csvCount !== $dbCount) {
                $errorMessage = sprintf(
                    "Factura '%s': Conteo de registros no coincide. En CSV: %d, En DB (glosa > 0): %d.",
                    $facturaId,
                    $csvCount,
                    $dbCount
                );

                // Recuperar los números de fila asociados a esta factura para añadir errores
                $rowNumbersForFactura = Redis::lrange("csv_factura_rows:{$this->currentBatchId}:{$facturaId}", 0, -1);

                foreach ($rowNumbersForFactura as $rowNumber) {
                    $validationService->addError(
                        (int)$rowNumber,
                        'FACTURA_ID',
                        $errorMessage,
                        'incomplete_invoice_count_mismatch',
                        $facturaId,
                        '{}'
                    );
                }
                Log::warning("Validación de factura completa fallida para FACTURA_ID: {$facturaId}. Se encontraron discrepancias en los conteos.");
            }
        }
    }


    /**
     * Retrieves errors from Redis for the current batch and inserts them into the database.
     */
    protected function storeErrorsFromRedis(): void
    {
        $errorKey = "import_errors:{$this->currentBatchId}";
        Log::info("Attempting to retrieve errors from Redis key: {$errorKey}");

        $rawErrors = Redis::lrange($errorKey, 0, -1);

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
                }
            }
            Log::info(sprintf('Stored %d validation errors to process_batche_errors table.', $totalErrors));
            Log::info('Finished inserting all errors into process_batche_errors table.');
        } else {
            Log::info("No valid errors to insert after decoding for batch ID: {$this->currentBatchId}");
        }
        Redis::del($errorKey);
    }

    private function handleImport(string $filePath): void
    {
        $this->import11Concurrent($filePath);
    }

    private function import11Concurrent(string $filePath): void
    {
        Log::info("iniciando import11Concurrent");
        $now = now()->format('Y-m-d H:i:s');
        $numberOfProcesses = 10;
        $tasks = [];
        for ($i = 0; $i < $numberOfProcesses; $i++) {
            $tasks[] = function () use ($filePath, $i, $numberOfProcesses, $now) {
                DB::reconnect();
                $handle = fopen($filePath, 'r');
                fgets($handle); // Skip header
                $currentLine = 0;
                $customers = [];
                while (($line = fgets($handle)) !== false) {
                    if ($currentLine++ % $numberOfProcesses !== $i) {
                        continue;
                    }
                    $row = str_getcsv($line,';');
                    $customers[] = [
                        'id' => Str::uuid(),
                        'auditory_final_report_id' => $row[0],
                        'response_status' => $row[29],
                        'autorization_number' => $row[30],
                        'accepted_value_ips' => (float)$row[31],
                        'accepted_value_eps' => (float)$row[32],
                        'eps_ratified_value' => (float)$row[33],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    if (count($customers) === 1000) {
                        DB::table('conciliation_results')->insert($customers);
                        $customers = [];
                    }
                }
                if (! empty($customers)) {
                    DB::table('conciliation_results')->insert($customers);
                }
                fclose($handle);
                return true;
            };
        }
        Concurrency::run($tasks);
    }
}
