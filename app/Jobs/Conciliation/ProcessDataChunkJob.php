<?php

namespace App\Jobs\Conciliation;

use App\Events\ImportProgressEvent;
use App\Imports\ChunkDataImport;
use App\Models\ProcessBatch;
use App\Services\Conciliation\ConciliationValidator;
use App\Services\ProcessBatchService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ProcessDataChunkJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5; // Permitir 5 intentos
    public $timeout = 3600; // 1 hora

    public function __construct(
        private string $filePath,
        private int $startRow,
        private int $chunkSize,
        private array $headers,
    ) {}

    public function handle()
    {
        $batchId = $this->batch()->id;
        Log::info("Iniciando ProcessDataChunkJob", [
            'batch_id' => $batchId,
            'start_row' => $this->startRow,
            'chunk_size' => $this->chunkSize,
            'file_path' => $this->filePath
        ]);

        try {
            $startTime = microtime(true);
            $rows = $this->readExcelChunk();
            $processedRowsInChunk = count($rows);
            Log::info("Chunk leído", [
                'batch_id' => $batchId,
                'row_count' => $processedRowsInChunk,
                'duration' => microtime(true) - $startTime
            ]);

            $validator = new ConciliationValidator($batchId);
            Log::info("Validador inicializado", ['batch_id' => $batchId]);

            foreach ($rows as $index => $row) {
                $actualRowNumber = $this->startRow + $index;
                $formattedRow = $this->mapToAssociativeArray($row, $this->headers);
                Log::debug("Procesando fila {$actualRowNumber}", [
                    'batch_id' => $batchId,
                    'formatted_row' => $formattedRow
                ]);

                try {
                    $errors = $validator->validate(
                        $formattedRow,
                        $row,
                        $actualRowNumber,
                        $this->headers
                    );
                    Log::debug("Fila {$actualRowNumber} validada", [
                        'batch_id' => $batchId,
                        'errors' => $errors
                    ]);

                    Redis::hincrby("batch:{$batchId}:progress", 'processed_records', 1);

                    if (!empty($errors)) {
                        foreach ($errors as $error) {
                            $error['batch_id'] = $batchId;
                            Redis::rpush("batch:{$batchId}:errors", json_encode($error));
                            Redis::hincrby("batch:{$batchId}:progress", 'error_count', 1);
                        }
                    } else {
                        Redis::rpush("batch:{$batchId}:staged_data", json_encode($formattedRow));
                    }

                    $totalProcessedRecords = Redis::hget("batch:{$batchId}:progress", 'processed_records') ?? 0;
                    $totalErrorCount = Redis::hget("batch:{$batchId}:progress", 'error_count') ?? 0;

                    Log::debug("Emitiendo evento para fila {$actualRowNumber}", [
                        'batch_id' => $batchId,
                        'total_processed' => $totalProcessedRecords,
                        'total_errors' => $totalErrorCount
                    ]);

                    event(new ImportProgressEvent(
                        $batchId,
                        (int)$totalProcessedRecords,
                        'Procesando datos',
                        (int)$totalErrorCount,
                        'active',
                        $actualRowNumber
                    ));

                } catch (Throwable $e) {
                    Log::error("Error procesando fila {$actualRowNumber}", [
                        'batch_id' => $batchId,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'row_data' => $row
                    ]);
                    Redis::rpush("batch:{$batchId}:errors", json_encode([
                        'row_number' => $actualRowNumber,
                        'column_name' => 'SYSTEM_ERROR',
                        'error_message' => 'Error inesperado: ' . $e->getMessage(),
                        'error_type' => 'system_processing_error',
                        'error_value' => null,
                        'original_data' => $formattedRow,
                        'timestamp' => now()->toISOString(),
                    ]));
                    Redis::hincrby("batch:{$batchId}:progress", 'error_count', 1);
                    Redis::hincrby("batch:{$batchId}:progress", 'processed_records', 1);

                    $totalProcessedRecords = Redis::hget("batch:{$batchId}:progress", 'processed_records') ?? 0;
                    $totalErrorCount = Redis::hget("batch:{$batchId}:progress", 'error_count') ?? 0;

                    event(new ImportProgressEvent(
                        $batchId,
                        (int)$totalProcessedRecords,
                        'Procesando datos',
                        (int)$totalErrorCount,
                        'active',
                        $actualRowNumber
                    ));
                }
            }

            Log::info("Actualizando processed_records en base de datos", ['batch_id' => $batchId]);
            ProcessBatchService::incrementProcessedRecords($batchId, $processedRowsInChunk);
            Log::info("Chunk procesado exitosamente", [
                'batch_id' => $batchId,
                'duration' => microtime(true) - $startTime
            ]);

            // Limpiar claves de Redis
            Log::info("Limpiando claves de Redis", ['batch_id' => $batchId]);
            Redis::del("batch:{$batchId}:progress");
            Redis::del("batch:{$batchId}:errors");
            Redis::del("batch:{$batchId}:staged_data");

        } catch (Throwable $e) {
            Log::error("Error crítico en ProcessDataChunkJob", [
                'batch_id' => $batchId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'start_row' => $this->startRow,
                'chunk_size' => $this->chunkSize,
                'file_path' => $this->filePath
            ]);
            Redis::rpush("batch:{$batchId}:errors", json_encode([
                'row_number' => $this->startRow,
                'column_name' => 'SYSTEM_ERROR',
                'error_message' => 'Error crítico en chunk: ' . $e->getMessage(),
                'error_type' => 'system_chunk_error',
                'original_data' => null,
                'timestamp' => now()->toISOString(),
            ]));
            Redis::hincrby("batch:{$batchId}:progress", 'error_count', 1);
            throw $e; // Relanzar la excepción para marcar el job como fallido
        }
    }

    private function readExcelChunk(): array
    {
        if (!file_exists($this->filePath)) {
            Log::error("Archivo no encontrado", [
                'file_path' => $this->filePath,
                'batch_id' => $this->batch()->id
            ]);
            throw new \Exception("Archivo no encontrado: {$this->filePath}");
        }

        $startTime = microtime(true);
        try {
            $import = new ChunkDataImport($this->startRow, $this->chunkSize);
            $data = Excel::toArray($import, $this->filePath)[0];
            Log::info("Tiempo de lectura del chunk", [
                'batch_id' => $this->batch()->id,
                'start_row' => $this->startRow,
                'chunk_size' => $this->chunkSize,
                'row_count' => count($data),
                'duration' => microtime(true) - $startTime
            ]);
            return $data ?? [];
        } catch (Throwable $e) {
            Log::error("Error al leer el archivo Excel", [
                'batch_id' => $this->batch()->id,
                'file_path' => $this->filePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function mapToAssociativeArray(array $row, array $headers): array
    {
        $formattedRow = [];
        foreach ($headers as $index => $header) {
            $formattedRow[$header] = $row[$index] ?? null;
        }
        return $formattedRow;
    }
}
