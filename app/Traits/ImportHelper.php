<?php

namespace App\Traits;

use App\Models\AuditoryFinalReport;
use App\Services\CsvValidationService;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

trait ImportHelper
{
    protected float $benchmarkStartTime;
    protected int $benchmarkStartMemory;
    protected int $startRowCount;
    protected int $startQueries;
    protected string $currentBatchId;

    public function handle(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        $this->currentBatchId = (string) Str::uuid();
        $filePath = base_path('prueba10mil.csv');

        $this->startBenchmark();

        try {
            // Paso 1: Extraer IDs únicos del CSV para la validación de suma de glosa
            $this->info("Extrayendo IDs únicos del CSV para precarga de glosa...");
            $csvIdsForGlosaSum = $this->getUniqueIdsFromCsv($filePath, 'ID');
            $this->info(sprintf("Encontrados %d IDs únicos en el CSV para precarga de glosa.", count($csvIdsForGlosaSum)));
            Log::info('IDs únicos extraídos del CSV para glosa (primeros 10):', array_slice($csvIdsForGlosaSum, 0, 10));

            // Paso 2: Precargar solo los datos de AuditoryFinalReport relevantes a Redis para la validación de suma de glosa
            if (!empty($csvIdsForGlosaSum)) {
                $this->info("Precargando datos de auditory_final_reports a Redis para los IDs del CSV (validación de suma)...");
                $this->preloadAuditoryGlosaForCsvIds($csvIdsForGlosaSum);
                $this->info("Precarga de datos de glosa completada.");
            } else {
                $this->warn("No se encontraron IDs en el CSV para precargar datos de auditoría para la validación de suma.");
            }

            // Paso 3: Validar cabeceras CSV y filas (CsvValidationService ahora también recolecta datos para la validación de facturas completas)
            $validationService = new CsvValidationService($this->currentBatchId);
            $errors = $validationService->validateCsv($filePath); // ESTA ES LA LLAMADA CORRECTA

            // Paso 4: Obtener los FACTURA_ID únicos del CSV que se recolectaron durante validateRows
            $this->info("Obteniendo FACTURA_ID únicos del CSV para la validación de facturas completas...");
            $uniqueFacturaIdsFromCsv = Redis::smembers("csv_unique_factura_ids:{$this->currentBatchId}");
            $this->info(sprintf("Encontrados %d FACTURA_ID únicos en el CSV.", count($uniqueFacturaIdsFromCsv)));
            Log::info('FACTURA_ID únicos extraídos del CSV (primeros 10):', array_slice($uniqueFacturaIdsFromCsv, 0, 10));


            // Paso 5: Precargar los conteos de IDs de la DB para la validación de facturas completas
            if (!empty($uniqueFacturaIdsFromCsv)) {
                $this->info("Precargando conteos de FACTURA_ID de auditory_final_reports (valor_glosa > 0) a Redis...");
                $this->preloadDbFacturaGlosaCounts($uniqueFacturaIdsFromCsv);
                $this->info("Precarga de conteos de factura completada.");
            } else {
                $this->warn("No se encontraron FACTURA_ID en el CSV para precargar conteos de factura completa.");
            }

            // Paso 6: Realizar la validación de facturas completas
            $this->info("Realizando validación de facturas completas...");
            $this->performFacturaCompletaValidation($validationService);
            $this->info("Validación de facturas completas finalizada.");

            // Recargar errores después de la validación de facturas completas
            $errors = array_merge($errors, $validationService->getErrors());


            if (!empty($errors)) {
                $this->error('Validation errors found:');
                foreach ($errors as $error) {
                    $this->line(sprintf(
                        'Row %d, Column %s: %s (%s)',
                        $error['row_number'],
                        $error['column_name'],
                        $error['error_message'],
                        $error['error_type']
                    ));
                }
                $this->storeErrorsFromRedis();
                return;
            }

            $this->info('CSV headers and rows are valid. Proceeding with import...');
            $this->handleImport($filePath);

        } catch (\Exception $e) {
            $this->error(get_class($e) . ' ' . Str::of($e->getMessage())->limit(100)->value());
            Log::error('Error durante la importación:', [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->storeErrorsFromRedis();
        } finally {
            $this->endBenchmark();
            // Limpiar todas las claves de Redis relacionadas con este batch
            Log::info("Limpiando claves de Redis para el batch actual...");
            $keysToDelete = Redis::keys("import_errors:{$this->currentBatchId}");
            $keysToDelete = array_merge($keysToDelete, Redis::keys("auditory_glosa:{$this->currentBatchId}:*"));
            $keysToDelete = array_merge($keysToDelete, Redis::keys("csv_factura_total_counts:{$this->currentBatchId}")); // Nueva clave
            $keysToDelete = array_merge($keysToDelete, Redis::keys("csv_factura_rows:{$this->currentBatchId}:*"));
            $keysToDelete = array_merge($keysToDelete, Redis::keys("db_factura_total_glosa_counts:{$this->currentBatchId}")); // Nueva clave
            $keysToDelete = array_merge($keysToDelete, Redis::keys("csv_unique_factura_ids:{$this->currentBatchId}"));


            if (!empty($keysToDelete)) {
                Redis::del($keysToDelete);
                Log::info(sprintf("Eliminadas %d claves de Redis.", count($keysToDelete)));
            } else {
                Log::info("No se encontraron claves de Redis para limpiar para este batch.");
            }
        }
    }

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
        $this->newLine();
        $this->line(sprintf(
            '⚡ <bg=bright-blue;fg=black> TIME: %s </> <bg=bright-green;fg=black> MEM: %sMB </> <bg=bright-yellow;fg=black> SQL: %s </> <bg=bright-magenta;fg=black> ROWS: %s </>',
            $formattedTime,
            $memoryUsage,
            number_format($queriesCount),
            number_format($rowDiff)
        ));
        $this->newLine();
    }

    /**
     * Extrae todos los IDs únicos de una columna específica del CSV.
     */
    protected function getUniqueIdsFromCsv(string $filePath, string $columnName): array
    {
        $ids = [];
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            $this->addError(0, 'file', 'Could not open CSV file.', 'file_error', $filePath, '');
            return [];
        }

        $headers = fgetcsv($handle, 0, ';');
        if ($headers && !empty($headers[0])) {
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        }
        $columnIndex = array_search($columnName, $headers);

        if ($columnIndex === false) {
            $this->error("La columna '{$columnName}' no se encontró en el archivo CSV. Asegúrate de que la cabecera esté presente.");
            Log::error("Error: Columna '{$columnName}' no encontrada en el CSV.", ['headers' => $headers]);
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
     */
    protected function preloadAuditoryGlosaForCsvIds(array $csvIds): void
    {
        $keysToDelete = Redis::keys("auditory_glosa:{$this->currentBatchId}:*");
        if (!empty($keysToDelete)) {
            Redis::del($keysToDelete);
        }

        $preloadStartTime = microtime(true);
        $count = 0;
        $chunkSize = 1000;

        foreach (array_chunk($csvIds, $chunkSize) as $idChunk) {
            AuditoryFinalReport::select('id', 'valor_glosa')
                ->whereIn('id', $idChunk)
                ->orderBy('id')
                ->chunk(1000, function ($reports) use (&$count) {
                    $pipeline = Redis::pipeline();
                    foreach ($reports as $report) {
                        $redisKey = "auditory_glosa:{$this->currentBatchId}:{$report->id}";
                        $pipeline->set($redisKey, (string) $report->valor_glosa);
                        $count++;
                    }
                    $pipeline->execute();
                });
        }

        $preloadEndTime = microtime(true);
        $this->info(sprintf("Precargados %d registros de auditory_final_reports (glosa) en Redis en %.2f segundos.", $count, ($preloadEndTime - $preloadStartTime)));
        Log::info(sprintf("Resumen de precarga de glosa: %d registros en %.2f segundos.", $count, ($preloadEndTime - $preloadStartTime)));
    }

    /**
     * Precarga los conteos de FACTURA_ID de AuditoryFinalReport (donde valor_glosa > 0) a Redis.
     * Almacena conteos totales de FACTURA_ID.
     */
    protected function preloadDbFacturaGlosaCounts(array $csvFacturaIds): void
    {
        $keysToDelete = Redis::keys("db_factura_total_glosa_counts:{$this->currentBatchId}");
        if (!empty($keysToDelete)) {
            Redis::del($keysToDelete);
        }

        $preloadStartTime = microtime(true);
        $count = 0;
        $chunkSize = 1000;

        // Usar un solo hash para todos los conteos de DB
        $redisHashKey = "db_factura_total_glosa_counts:{$this->currentBatchId}";
        $pipeline = Redis::pipeline();

        // Obtener los conteos de la DB en chunks
        foreach (array_chunk($csvFacturaIds, $chunkSize) as $facturaIdChunk) {
            AuditoryFinalReport::select('factura_id', DB::raw('COUNT(*) as total_count'))
                ->whereIn('factura_id', $facturaIdChunk)
                ->where('valor_glosa', '>', 0)
                ->groupBy('factura_id')
                ->orderBy('factura_id') // AÑADIDO: Ordenar por factura_id para compatibilidad con only_full_group_by
                ->chunk(1000, function ($results) use (&$count, $pipeline, $redisHashKey) {
                    foreach ($results as $result) {
                        $pipeline->hset($redisHashKey, $result->factura_id, $result->total_count);
                        $count++;
                    }
                });
        }
        $pipeline->execute(); // Ejecutar el pipeline al final de todos los chunks

        $preloadEndTime = microtime(true);
        $this->info(sprintf("Precargados %d conteos de FACTURA_ID de DB (glosa > 0) en Redis en %.2f segundos.", $count, ($preloadEndTime - $preloadStartTime)));
        Log::info(sprintf("Resumen de precarga de conteos de factura: %d registros en %.2f segundos.", $count, ($preloadEndTime - $preloadStartTime)));
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
                        'FACTURA_ID', // Columna específica del error: AHORA ES FACTURA_ID
                        $errorMessage,
                        'incomplete_invoice_count_mismatch',
                        $facturaId, // Valor del error para depuración
                        '{}' // Placeholder para original_data
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
                    $this->error('Failed to bulk insert errors into process_batche_errors: ' . $e->getMessage());
                    Log::error('Database insertion failed for errors chunk:', [
                        'exception' => $e->getMessage(),
                        'code' => $e->getCode(),
                        'trace' => $e->getTraceAsString(),
                        'errors_attempted_to_insert' => $chunk
                    ]);
                }
            }
            $this->info(sprintf('Stored %d validation errors to process_batche_errors table.', $totalErrors));
            Log::info('Finished inserting all errors into process_batche_errors table.');
        } else {
            Log::info("No valid errors to insert after decoding for batch ID: {$this->currentBatchId}");
        }
        Redis::del($errorKey);
    }

    private function handleImport(string $filePath): void
    {
        // Este método será reintroducido con la lógica de importación principal
        // Una vez que todas las validaciones estén en su lugar.
        $this->import11Concurrent($filePath);
    }

    private function import11Concurrent(string $filePath): void
    {
        Log::info("iniciando import11Concurrent");
        // 100 168ms
        // 1K 172ms
        // 10K 234ms
        // 100K 595ms
        // 1M 4.36s
        // 2M 8.8s
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
                    // Each process takes every Nth line
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
