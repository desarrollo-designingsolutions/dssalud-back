<?php

namespace App\Services;

use App\Helpers\Constants;
use App\Models\ProcessBatch;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProcessBatchService
{
    const CHUNK_SIZE = 1000;
    const BASE_PATH = 'process_logs';

    public static function initProcess(string $batchId, string $companyId, string $user_id, int $totalRecords)
    {
        // Crear registro en BD
        $log = ProcessBatch::create([
            'batch_id' => $batchId,
            'company_id' => $companyId,
            'user_id' => $user_id,
            'total_records' => $totalRecords,
            'status' => 'processing',
            'errors_root_path' => '', // Se actualizará luego
            'metadata_path' => '', // Se actualizará luego
        ]);

        Log::info("Proceso iniciado en BD", ['batch_id' => $batchId]);
        return $log;
    }

    public static function saveErrors(string $batchId, array $errors)
    {
        try {
            $totalErrors = count($errors);
            $needsChunking = $totalErrors > self::CHUNK_SIZE;
            $errors_root_path = $needsChunking
                ? self::BASE_PATH . "/{$batchId}/errors/"
                : self::BASE_PATH . "/{$batchId}/errors.json";

            // 1. Guardar en archivo (como antes)
            $metadata = [
                'batch_id' => $batchId,
                'total_errors' => $totalErrors,
                'has_chunks' => $needsChunking,
                'created_at' => now()->toDateTimeString()
            ];

            $metadata_path = Storage::disk(Constants::DISK_FILES)->put(
                self::BASE_PATH . "/{$batchId}/metadata.json",
                json_encode($metadata, JSON_PRETTY_PRINT)
            );

            if ($needsChunking) {
                self::saveChunkedErrors($batchId, $errors);
            } else {
                Storage::disk(Constants::DISK_FILES)->put(
                    self::BASE_PATH . "/{$batchId}/errors.json",
                    json_encode($errors, JSON_PRETTY_PRINT)
                );
            }

            // 2. Actualizar BD
            $log = ProcessBatch::where('batch_id', $batchId)->firstOrFail();

            $log->update([
                'error_count' => $totalErrors,
                'errors_root_path' => $errors_root_path,
                'metadata_path' => $metadata_path,
                'status' => $totalErrors > 0 ? 'completed_with_errors' : 'completed'
            ]);

            Log::info("Proceso finalizado en BD", [
                'batch_id' => $batchId,
                'errors' => $totalErrors
            ]);
        } catch (\Exception $e) {
            ProcessBatch::where('batch_id', $batchId)
                ->update(['status' => 'failed']);

            Log::error("Error guardando proceso: " . $e->getMessage());
            throw $e;
        }
    }

    protected static function countErrorTypes(array $errors): array
    {
        return collect($errors)->groupBy('tipo_de_error')
            ->map->count()
            ->sortDesc()
            ->toArray();
    }



    protected static function saveChunkedErrors(string $batchId, array $errors)
    {
        $chunks = array_chunk($errors, self::CHUNK_SIZE);
        $chunkInfo = [];

        foreach ($chunks as $index => $chunk) {
            $chunkNumber = $index + 1;
            $filename = "chunk_{$chunkNumber}.json";
            $path = self::BASE_PATH . "/{$batchId}/errors/{$filename}";

            Storage::disk(Constants::DISK_FILES)->put($path, json_encode($chunk, JSON_PRETTY_PRINT));

            $chunkInfo[] = [
                'file' => $filename,
                'count' => count($chunk),
                'min_row' => min(array_column($chunk, 'fila')),
                'max_row' => max(array_column($chunk, 'fila'))
            ];
        }

        // Guardar índice de chunks
        Storage::disk(Constants::DISK_FILES)->put(
            self::BASE_PATH . "/{$batchId}/chunks_index.json",
            json_encode($chunkInfo, JSON_PRETTY_PRINT)
        );
    }

    public static function getErrors(string $batchId, int $page = 1, int $perPage = 200)
    {

        $processBatch= ProcessBatch::where("batch_id",$batchId)->first();

    //    return $metadata = json_decode(
    //         Storage::disk(Constants::DISK_FILES)->get(self::BASE_PATH . "/{$batchId}/metadata.json"),
    //         true
    //     );

        // Caso 1: Archivo único (<= 1k errores)
        if (!$processBatch['has_chunks']) {
            $allErrors = json_decode(
                Storage::disk(Constants::DISK_FILES)->get(self::BASE_PATH . "/{$batchId}/errors.json"),
                true
            );
            return [
                'data' => array_slice($allErrors, ($page - 1) * $perPage, $perPage),
                'meta' => [
                    'total' => $processBatch['error_count'],
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'is_chunked' => false
                ]
            ];
        }

        // Caso 2: Sistema de chunks (>1k errores)
        return self::getPaginatedFromChunks($batchId, $page, $perPage);
    }

    protected static function getPaginatedFromChunks(string $batchId, int $page, int $perPage)
    {
        $index = json_decode(
            Storage::disk(Constants::DISK_FILES)->get(self::BASE_PATH . "/{$batchId}/chunks_index.json"),
            true
        );

        // Cálculo de chunks necesarios
        $errors = [];
        $remaining = $perPage;
        $currentChunk = 0;
        $totalCollected = 0;
        $totalToSkip = ($page - 1) * $perPage;

        while ($remaining > 0 && $currentChunk < count($index)) {
            $currentChunk++;
            $chunkData = json_decode(
                Storage::disk(Constants::DISK_FILES)->get(self::BASE_PATH . "/{$batchId}/errors/chunk_{$currentChunk}.json"),
                true
            );

            // Saltar chunks completos si es necesario
            if ($totalToSkip >= count($chunkData)) {
                $totalToSkip -= count($chunkData);
                continue;
            }

            // Saltar elementos dentro del chunk
            $chunkData = array_slice($chunkData, $totalToSkip);
            $totalToSkip = 0;

            // Agregar al resultado
            $errors = array_merge($errors, array_slice($chunkData, 0, $remaining));
            $remaining = $perPage - count($errors);
        }

        return [
            'data' => $errors,
            'meta' => [
                'total' => array_reduce($index, fn($carry, $item) => $carry + $item['count'], 0),
                'current_page' => $page,
                'per_page' => $perPage,
                'is_chunked' => true,
                'chunks_accessed' => $currentChunk
            ]
        ];
    }
}
