<?php

namespace App\Services;

use App\Models\ProcessBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ProcessBatchService
{
    const CHUNK_SIZE = 1000;

    const BASE_PATH = 'process_logs';

    const REDIS_USED_QUEUES_KEY = 'import_system:used_queues';

    public static function initProcess(string $batchId, string $companyId, string $user_id, int $totalRecords, array $metadata)
    {
        $status = 'active';
        $metadata = json_encode($metadata);

        $log = ProcessBatch::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'batch_id' => $batchId,
            'company_id' => $companyId,
            'user_id' => $user_id,
            'total_records' => $totalRecords,
            'processed_records' => 0,
            'error_count' => 0,
            'status' => $status,
            'metadata' => $metadata,
        ]);

        return $log;
    }

    /**
     * Incrementa el número de registros procesados para un batch.
     *
     * @param  int  $count  El número de registros a añadir al contador.
     * @return int El nuevo total de registros procesados.
     */
    public static function incrementProcessedRecords(string $batchId, int $count): int
    {
        try {
            $processBatch = ProcessBatch::where('batch_id', $batchId)->first();
            if ($processBatch) {
                // <-- Aquí se actualiza el contador de registros procesados en la DB
                $processBatch->increment('processed_records', $count);

                return $processBatch->processed_records;
            }
        } catch (\Exception $e) {
            Log::error("Error incrementando registros procesados para batch {$batchId}: ".$e->getMessage());
        }

        return 0;
    }

    /**
     * Finaliza el proceso de un batch, actualizando el estado final y rutas de errores/metadata.
     */
    public static function finalizeProcess(string $batchId, int $finalErrorCount, string $finalStatus)
    {
        try {
            $processBatch = ProcessBatch::where('batch_id', $batchId)->firstOrFail();

            $processBatch->update([
                'error_count' => $finalErrorCount,
                'status' => $finalStatus,
            ]);
        } catch (\Exception $e) {
            Log::error("Error finalizando proceso para batch {$batchId}: ".$e->getMessage());
            ProcessBatch::where('batch_id', $batchId)->update(['status' => 'failed']);
            throw $e;
        }
    }

    protected static function countErrorTypes(array $errors): array
    {
        return collect($errors)->groupBy('error_type')
            ->map->count()
            ->sortDesc()
            ->toArray();
    }

    /**
     * Selects an available queue from a given list using Redis for atomic claiming.
     *
     * @param  array  $availableQueues  List of queue names, e.g., ['imports_1', 'imports_2']
     * @return string The name of the selected queue.
     *
     * @throws \Exception If no queues are available.
     */
    public static function selectAvailableQueue(array $availableQueues): string
    {
        foreach ($availableQueues as $queue) {
            // SADD retorna 1 si el elemento fue añadido (significa que no estaba antes)
            // SADD retorna 0 si el elemento ya existía en el set (significa que está en uso)
            if (Redis::connection('redis_6380')->sadd(self::REDIS_USED_QUEUES_KEY, $queue) === 1) {
                return $queue;
            }
        }

        Log::warning('No available queues found.');
        throw new \Exception('No hay colas disponibles en este momento.');
    }

    /**
     * Releases a queue, marking it as available again in Redis.
     *
     * @param  string  $queueName  The name of the queue to release.
     */
    public static function releaseQueue(string $queueName): void
    {
        // SREM retorna 1 si el elemento fue removido (significa que existía)
        // SREM retorna 0 si el elemento no fue encontrado en el set
        if (Redis::connection('redis_6380')->srem(self::REDIS_USED_QUEUES_KEY, $queueName) === 1) {
        } else {
            Log::warning("Attempted to release queue '{$queueName}' but it was not found in the used queues set. It might have already been released or never acquired.");
        }
    }
}
