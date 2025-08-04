<?php

namespace App\Jobs\Conciliation;

use App\Events\ImportProgressEvent;
use App\Models\ProcessBatch;
use App\Services\Conciliation\ConciliationValidator;
use App\Services\ProcessBatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class FinalizeImportDecisionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600;

    public function __construct(
        private string $currentBatchId,
        private string $originalFilePath
    ) {}

    public function handle()
    {
        $batchIdToUse = $this->currentBatchId;
        $finalErrorCount = 0; // Inicializar aquí para asegurar que siempre tenga un valor

        try {
            log::info("FinalizeImportDecisionJob iniciado para batch {$batchIdToUse}");
            // Obtener el registro ProcessBatch para acceder a todos los datos necesarios
            $processBatchRecord = ProcessBatch::where('batch_id', $batchIdToUse)->first();
            $totalRecords = $processBatchRecord ? $processBatchRecord->total_records : 0;
            $processedRecords = $processBatchRecord ? $processBatchRecord->processed_records : 0;

            // Obtener la cuenta de errores acumulados hasta ahora (antes de la validación final)
            $initialRedisErrorCount = Redis::llen("batch:{$batchIdToUse}:errors");

            log::info("FinalizeImportDecisionJob: Procesando batch {$batchIdToUse} con {$processedRecords} registros procesados y {$initialRedisErrorCount} errores acumulados.");


            // Emitir evento de progreso: Finalizando
            event(new ImportProgressEvent(
                $batchIdToUse,
                $processedRecords,
                'Finalizando importación', // currentAction
                $initialRedisErrorCount, // Usar el conteo actual de errores
                'finalizing', // backendStatus
                $processedRecords // currentElement (último registro procesado)
            ));
            Log::info("message: Finalizando importación para batch {$batchIdToUse}");

            // Ejecutar validaciones finales que puedan añadir más errores a Redis
            $conciliationValidator = new ConciliationValidator($batchIdToUse);
            $finalValidationErrors = $conciliationValidator->finalizeValidation();

            if (! empty($finalValidationErrors)) {
                Log::warning("Errores de validación final de conciliación encontrados para batch {$batchIdToUse}.");
                foreach ($finalValidationErrors as $error) {
                    $error['batch_id'] = $batchIdToUse;
                    $encodedError = json_encode($error);
                    if ($encodedError === false) {
                        Log::error('FinalizeImportDecisionJob: Fallo al codificar JSON para error de validación final: '.json_last_error_msg(), ['error_data' => $error]);

                        continue;
                    }
                    Redis::rpush("batch:{$batchIdToUse}:errors", $encodedError);
                }
            }

            // Obtener el conteo TOTAL de errores de Redis después de todas las validaciones
            // Este es el conteo final que usaremos para la lógica de decisión y el evento final.
            $finalErrorCount = Redis::llen("batch:{$batchIdToUse}:errors");

            // Persistir todos los errores acumulados de Redis a la DB
            // Este método también limpia la lista de errores de Redis después de persistirlos.
            $this->persistErrorsFromRedisToDb($batchIdToUse);

            $finalStatus = 'completed';
            if ($finalErrorCount > 0) {
                Log::warning("Batch {$batchIdToUse} tiene {$finalErrorCount} errores. Descartando datos de staging de Redis.");
                // Eliminar la clave de Redis para los datos de staging si hay errores
                Redis::del("batch:{$batchIdToUse}:staged_data");
                $finalStatus = 'failed'; // O 'completed_with_errors' si quieres un estado intermedio
            } else {
                DB::transaction(function () use ($batchIdToUse) {
                    $stagedRecordsJson = Redis::lrange("batch:{$batchIdToUse}:staged_data", 0, -1);
                    $insertBatchSize = 1000;
                    $recordsToInsert = [];

                    foreach ($stagedRecordsJson as $recordJson) {
                        $formattedRow = json_decode($recordJson, true); // Decodificar el JSON a array asociativo

                        if (json_last_error() !== JSON_ERROR_NONE) {
                            Log::error('Fallo al decodificar JSON de staged_data desde Redis: '.json_last_error_msg(), ['json' => $recordJson]);

                            continue;
                        }

                        // Aquí se aplican las transformaciones y se añaden los campos de control
                        $recordsToInsert[] = [
                            'id' => Str::uuid(),
                            'auditory_final_report_id' => $formattedRow['ID'] ?? null,
                            'response_status' => $formattedRow['ESTADO_RESPUESTA'] ?? null,
                            'autorization_number' => $formattedRow['NUMERO_DE_AUTORIZACION'] ?? null,
                            'accepted_value_ips' => (float) ($formattedRow['VALOR_ACEPTADO_IPS'] ?? 0.0),
                            'accepted_value_eps' => (float) ($formattedRow['VALOR_ACEPTADO_EPS'] ?? 0.0),
                            'eps_ratified_value' => (float) ($formattedRow['VALOR_RATIFICADO_EPS'] ?? 0.0),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];

                        if (count($recordsToInsert) >= $insertBatchSize) {
                            DB::table('conciliation_results')->insert($recordsToInsert); // <-- Reemplaza con tu tabla final
                            $recordsToInsert = [];
                        }
                    }

                    if (! empty($recordsToInsert)) {
                        DB::table('conciliation_results')->insert($recordsToInsert); // <-- Reemplaza con tu tabla final
                    }

                    // Eliminar la clave de Redis para los datos de staging después de la inserción exitosa
                    Redis::del("batch:{$batchIdToUse}:staged_data");
                });
                $finalStatus = 'completed';
            }

            // Finalizar el proceso en la DB (ProcessBatchService) con el conteo final de errores
            ProcessBatchService::finalizeProcess($batchIdToUse, $finalErrorCount, $finalStatus);

            // Eliminar el archivo original
            if (Storage::exists($this->originalFilePath)) {
                Storage::delete($this->originalFilePath);
            }

            // Emitir evento de progreso final
            $currentAction = $finalErrorCount > 0 ? 'Se encontraron errores que se deben corregir' : 'Importación finalizada con éxito';
            event(new ImportProgressEvent(
                $batchIdToUse,
                $totalRecords, // processedRecords
                $currentAction, // currentAction
                $finalErrorCount, // errorCount
                $finalStatus, // backendStatus
                $totalRecords // currentElement (total de registros)
            ));

        } catch (Throwable $e) {
            Log::error("Error en FinalizeImportDecisionJob para batch {$batchIdToUse}: ".$e->getMessage());

            // Asegurarse de persistir los errores a la DB incluso en caso de fallo crítico aquí
            $this->persistErrorsFromRedisToDb($batchIdToUse);
            // Obtener el conteo final de errores después de la persistencia en caso de fallo
            $finalErrorCount = Redis::llen("batch:{$batchIdToUse}:errors"); // Esto será 0 si persistErrorsFromRedisToDb lo vacía

            $processBatchRecord = ProcessBatch::where('batch_id', $batchIdToUse)->first();
            $processedRecordsAtFailure = $processBatchRecord ? $processBatchRecord->processed_records : 0;

            ProcessBatchService::finalizeProcess($batchIdToUse, $finalErrorCount, 'failed');
            event(new ImportProgressEvent(
                $batchIdToUse,
                $processedRecordsAtFailure,
                'Importación fallida',
                $finalErrorCount,
                'failed', // backendStatus
                $processedRecordsAtFailure // currentElement
            ));
            // Limpiar staged_data de Redis en caso de fallo crítico
            Redis::del("batch:{$batchIdToUse}:staged_data");
            throw $e;
        }
    }

    private function persistErrorsFromRedisToDb(string $batchId): void
    {
        $redisKey = "batch:{$batchId}:errors";
        $errorsFromRedis = Redis::lrange($redisKey, 0, -1);
        if (empty($errorsFromRedis)) {
            return;
        }
        $errorsToInsert = [];
        $insertBatchSize = 500;
        foreach ($errorsFromRedis as $errorJson) {
            $errorData = json_decode($errorJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Fallo al decodificar JSON de error desde Redis: '.json_last_error_msg(), ['json' => $errorJson]);

                continue;
            }
            $errorsToInsert[] = [
                'id' => Str::uuid(),
                'batch_id' => $batchId,
                'row_number' => $errorData['row_number'] ?? null,
                'column_name' => $errorData['column_name'] ?? null,
                'error_message' => $errorData['error_message'] ?? 'Error desconocido',
                'error_type' => $errorData['error_type'] ?? 'unknown',
                'original_data' => json_encode($errorData['original_data'] ?? null),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (count($errorsToInsert) >= $insertBatchSize) {
                DB::table('process_batche_errors')->insert($errorsToInsert);
                $errorsToInsert = [];
            }
        }
        if (! empty($errorsToInsert)) {
            DB::table('process_batche_errors')->insert($errorsToInsert);
        }
        // Limpiar la lista de errores de Redis después de persistirlos
        Redis::del($redisKey);
    }
}
