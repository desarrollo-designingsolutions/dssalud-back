<?php

namespace App\Jobs\Conciliation;

use App\Events\ImportCompletedEvent;
use App\Events\ImportProgressEvent;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Services\Conciliation\ConciliationValidator;
use App\Services\ProcessBatchService;
use Illuminate\Support\Str;

class ProcessChunkJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected string $companyId,
        protected string $user_id,
        protected array $headers,
        protected array $rows,
        protected int $sheetIndex,
        protected int $chunkIndex,
        protected int $totalRecords,
        protected array $initialMetadata = []
    ) {}

    public function handle()
    {
        if ($this->batch()->cancelled()) {
            return;
        }

        try {

            $batchMetadata = Cache::get("batch_metadata_{$this->batch()->id}", $this->initialMetadata);


            $cacheKey = "batch_data_excel_{$this->batch()->id}";
            $lock = Cache::lock("lock_{$cacheKey}", 10);

            try {
                $lock->block(5); // Espera hasta 5 segundos para obtener el bloqueo
                $existingRows = Cache::get($cacheKey, []);
                $newRows = array_merge($existingRows, $this->rows);
                Cache::put($cacheKey, $newRows, now()->addHours(2));
            } finally {
                $lock->release();
            }


            $validator = new ConciliationValidator();
            $memoryUsage = memory_get_usage(true);
            $startTime = microtime(true);

            DB::transaction(function () use ($batchMetadata, $memoryUsage, $startTime, $validator) {
                $totalRows = count($this->rows);
                $totalErrors = 0;
                $recordsWithErrors = 0;
                $warningsCount = 0;
                $allErrors = [];

                foreach ($this->rows as $index => $row) {
                    $formattedRow = array_combine($this->headers, $row);
                    $formattedRow = array_map('trim', $formattedRow);

                    $processedRecords = Cache::increment("batch_processed_{$this->batch()->id}", 1);
                    $chunkProgress = intval((($index + 1) / $totalRows) * 100);
                    $generalProgress = $this->totalRecords > 0 ? intval(($processedRecords / $this->totalRecords) * 100) : 0;
                    $generalProgress = min($generalProgress, 100);

                    // Validar el registro
                    $errors = $validator->validate($formattedRow, $row, $index + 1, $this->headers, $this->batch()->id);

                    if (!empty($errors)) {
                        $totalErrors += count($errors);
                        $recordsWithErrors++;
                        $allErrors = array_merge($allErrors, $errors);
                    }

                    event(new ImportProgressEvent(
                        $this->batch()->id,
                        $chunkProgress,
                        $formattedRow['NUMERO_FACTURA'] ?? 'N/A',
                        'Procesando registros',
                        $this->buildMetadata(
                            $batchMetadata,
                            $index,
                            $totalRows,
                            $processedRecords,
                            $generalProgress,
                            $totalErrors,
                            $warningsCount,
                            $memoryUsage,
                            $recordsWithErrors
                        )
                    ));
                }

                // Validación final de facturas completas (solo en el último chunk)
                if ($this->isLastChunk()) {
                    $invoiceErrors = $validator->finalizeValidation();
                    if (!empty($invoiceErrors)) {
                        $totalErrors += count($invoiceErrors);
                        $recordsWithErrors += count(array_unique(array_column($invoiceErrors, 'fila')));
                        $allErrors = array_merge($allErrors, $invoiceErrors);
                    }
                }

                Cache::put("batch_total_errors_{$this->batch()->id}", $totalErrors, now()->addHours(2));
                Cache::put("batch_records_with_errors_{$this->batch()->id}", $recordsWithErrors, now()->addHours(2));
                Cache::put("batch_warnings_{$this->batch()->id}", $warningsCount, now()->addHours(2));

                if (!empty($allErrors)) {
                    $this->storeErrorsInCache($allErrors);
                }
            });

             logMessage("batch in handle");
            logMessage($this->batch()->finished());

            $this->checkIfCompleted($batchMetadata);
        } catch (\Exception $e) {
            Cache::increment("batch_total_errors_{$this->batch()->id}", 1);
            throw $e;
        }
    }


    protected function storeErrorsInCache(array $errors): void
    {
        try {
            $batchId = $this->batch()->id;
            $cacheKey = "conciliation_errors_{$batchId}";

            Cache::put(
                $cacheKey,
                $errors,
                now()->addHours(2) // 2 horas de expiración
            );

            Log::info("Errores guardados en cache para el batch: {$batchId}", [
                'total_errors' => count($errors),
                'cache_key' => $cacheKey,
                'driver' => config('cache.default') // Para saber qué driver se está usando
            ]);
        } catch (\Exception $e) {
            Log::error("Error al guardar errores en cache: " . $e->getMessage());
        }
    }

    // Nuevo método para detectar el último chunk
    protected function isLastChunk(): bool
    {
        return $this->batch()->pendingJobs <= 1;
    }

    protected function checkIfCompleted(array $batchMetadata): void
    {
        $batch = $this->batch();
            logMessage("batch in checkIfCompleted");
            logMessage($batch->finished());


        if ($batch->pendingJobs <= 1) {
        // if ($batch->finished()) {
            $finalProcessedRecords = Cache::get("batch_processed_{$batch->id}", $this->totalRecords);
            $finalTotalErrors = Cache::get("batch_total_errors_{$batch->id}", 0);
            $finalRecordsWithErrors = Cache::get("batch_records_with_errors_{$batch->id}", 0);
            $finalWarnings = Cache::get("batch_warnings_{$batch->id}", 0);

            $metadata = $this->buildMetadata(
                $batchMetadata,
                0,
                0,
                $finalProcessedRecords,
                100,
                $finalTotalErrors,
                $finalWarnings,
                memory_get_usage(true),
                $finalRecordsWithErrors
            );

            event(new ImportProgressEvent(
                $batch->id,
                100,
                'Proceso completado',
                $finalTotalErrors > 0 ? 'Completado con errores' : 'Validación exitosa',
                $metadata
            ));

            logMessage("aaaaaa");


            if ($finalTotalErrors > 0) {
                $allErrors = Cache::get("conciliation_errors_{$batch->id}");
                ProcessBatchService::saveErrors($this->batch()->id, $allErrors);
            }

            logMessage(000000);

            // Guardar en la base de datos si no hay errores
            if ($finalTotalErrors == 0) {
                logMessage(1111);
                $dataExcel = Cache::get("batch_data_excel_{$batch->id}", []);
                if (empty($dataExcel)) {
                    Log::warning("No data found in cache for batch_id: {$batch->id}");
                } else {
                    logMessage(2222);

                    foreach (array_chunk($dataExcel, 1000) as $batchIndex => $chunk) {
                        $insertData = [];
                        logMessage(33333);

                        foreach ($chunk as $row) {
                            if ($formattedRow = array_combine($this->headers, $row)) {
                                $formattedRow = array_map('trim', $formattedRow);
                                $insertData[] = [
                                    'id' =>  Str::uuid(),
                                    'auditory_final_report_id' => $formattedRow['ID'] ?? null,
                                    'response_status' => $formattedRow['ESTADO_RESPUESTA'] ?? null,
                                    'autorization_number' => $formattedRow['NUMERO_DE_AUTORIZACION'] ?? null,
                                    'accepted_value_ips' => (float) $formattedRow['VALOR_ACEPTADO_IPS'],
                                    'accepted_value_eps' => (float) $formattedRow['VALOR_ACEPTADO_EPS'],
                                    'eps_ratified_value' => (float) $formattedRow['VALOR_RATIFICADO_EPS'],
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ];
                            } else {
                                Log::error("Invalid row data: headers and row length mismatch", [
                                    'batch_id' => $batch->id,
                                    'row' => $row,
                                    'headers' => $this->headers,
                                ]);
                            }
                        }

                        if (!empty($insertData)) {
                            try {
                                logMessage(4444);
                                logMessage($insertData);

                                // DB::transaction(function () use ($insertData, $batch, $batchIndex) {
                                \App\Models\ConciliationResult::insert($insertData);
                                // });
                                Log::info("Successfully inserted batch {$batchIndex} with " . count($insertData) . " records for batch_id: {$batch->id}");
                            } catch (\Exception $e) {
                                Log::error("Failed to insert batch {$batchIndex} for batch_id: {$batch->id}", [
                                    'error' => $e->getMessage(),
                                    'batch_size' => count($insertData),
                                ]);
                            }
                        }
                    }
                }
            }


            // Limpiar cache
            $this->cleanupCache($batch->id);
        }
    }


    protected function cleanupCache(string $batchId): void
    {
        $hasErrors = Cache::has("conciliation_errors_{$batchId}");

        if (!$hasErrors) {
            Cache::forget("batch_total_errors_{$batchId}");
            Cache::forget("batch_records_with_errors_{$batchId}");
        }

        Cache::forget("batch_processed_{$batchId}");
        Cache::forget("batch_warnings_{$batchId}");
        Cache::forget("batch_metadata_{$batchId}");
    }

    protected function buildMetadata(
        array $batchMetadata,
        int $currentIndex,
        int $totalRows,
        int $processedRecords,
        int $generalProgress,
        int $errorsCount,
        int $warningsCount,
        int $memoryUsage,
        int $recordsWithErrors = 0
    ): array {
        $estimatedTimeRemaining = $this->calculateEstimatedTime(
            $batchMetadata['processing_start_time'] ?? null,
            $processedRecords,
            $this->totalRecords,
            $generalProgress
        );

        $processingSpeed = $this->calculateProcessingSpeed(
            $batchMetadata['processing_start_time'] ?? null,
            $processedRecords
        );

        return [
            'sheet' => $this->sheetIndex + 1,
            'chunk' => $this->chunkIndex + 1,
            'current_row' => $currentIndex + 1,
            'total_rows' => $totalRows,
            'total_records' => $this->totalRecords,
            'processed_records' => $processedRecords,
            'general_progress' => $generalProgress,
            'current_sheet' => $this->sheetIndex + 1,
            'total_sheets' => $batchMetadata['total_sheets'] ?? 1,
            'total_errors' => $errorsCount,
            'records_with_errors' => $recordsWithErrors,
            'warnings_count' => $warningsCount,
            'file_size' => $batchMetadata['file_size'] ?? 0,
            'processing_start_time' => $batchMetadata['processing_start_time'] ?? null,
            'last_activity' => now()->toDateTimeString(),
            'memory_usage' => $memoryUsage,
            'cpu_usage' => 0,
            'connection_status' => 'connected',
            'processing_speed' => $processingSpeed,
            'estimated_time_remaining' => $estimatedTimeRemaining,
            // Mantener compatibilidad con frontend existente
            'errors_count' => $errorsCount, // Alias de total_errors
        ];
    }

    protected function calculateProcessingSpeed(?string $startTime, int $processedRecords): int
    {
        if (!$startTime || $processedRecords === 0) {
            return 0;
        }

        try {
            $startTimestamp = strtotime($startTime);
            $currentTimestamp = time();
            $elapsedSeconds = $currentTimestamp - $startTimestamp;

            return $elapsedSeconds > 0 ? intval($processedRecords / $elapsedSeconds) : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    protected function calculateEstimatedTime(?string $startTime, int $processedRecords, int $totalRecords, int $progress): int
    {
        if (!$startTime || $progress === 0 || $totalRecords === 0) {
            return 0;
        }

        try {
            $startTimestamp = strtotime($startTime);
            $currentTimestamp = time();
            $elapsedSeconds = $currentTimestamp - $startTimestamp;

            if ($elapsedSeconds <= 0) {
                return 0;
            }

            $remainingProgress = 100 - $progress;
            $estimatedTotalTime = ($elapsedSeconds * 100) / $progress;
            $estimatedRemainingByProgress = $estimatedTotalTime - $elapsedSeconds;

            $estimatedRemainingByRecords = 0;
            if ($processedRecords > 0) {
                $recordsPerSecond = $processedRecords / $elapsedSeconds;
                $remainingRecords = $totalRecords - $processedRecords;
                $estimatedRemainingByRecords = $remainingRecords / $recordsPerSecond;
            }

            $finalEstimate = $estimatedRemainingByProgress;
            if ($estimatedRemainingByRecords > 0) {
                $finalEstimate = ($estimatedRemainingByProgress + $estimatedRemainingByRecords) / 2;
            }

            return max(0, intval($finalEstimate));
        } catch (\Exception $e) {
            return 0;
        }
    }
}
