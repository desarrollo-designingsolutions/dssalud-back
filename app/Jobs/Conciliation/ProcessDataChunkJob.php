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

    public $timeout = 3600; // Mantener tu timeout

    public function __construct(
        private string $filePath,
        private int $startRow,
        private int $chunkSize,
        private array $headers,
    ) {}

    public function handle()
    {
        $batchId = $this->batch()->id;

        try {

            $rows = $this->readExcelChunk();
            $processedRowsInChunk = count($rows);

            // Obtener el total de registros procesados ANTES de este chunk desde la DB
            $initialProcessedRecordsForBatch = ProcessBatch::where('batch_id', $batchId)->value('processed_records') ?? 0;

            $localProcessedInChunk = 0;
            $validator = new ConciliationValidator($batchId);

            foreach ($rows as $index => $row) {
                $actualRowNumber = $this->startRow + $index;
                $formattedRow = $this->mapToAssociativeArray($row, $this->headers);

                try {
                    $errors = $validator->validate(
                        $formattedRow,
                        $row,
                        $actualRowNumber,
                        $this->headers
                    );

                    if (! empty($errors)) {
                        foreach ($errors as $error) {
                            $error['batch_id'] = $batchId;
                            Redis::rpush("batch:{$batchId}:errors", json_encode($error));
                        }
                    }
                } catch (Throwable $e) {
                    Log::error("Error inesperado procesando fila {$actualRowNumber}: ".$e->getMessage(), ['row_data' => $row]);
                    Redis::rpush("batch:{$batchId}:errors", json_encode([
                        'row_number' => $actualRowNumber,
                        'column_name' => 'SYSTEM_ERROR',
                        'error_message' => 'Error inesperado: '.$e->getMessage(),
                        'error_type' => 'system_processing_error',
                        'error_value' => null,
                        'original_data' => $formattedRow,
                        'timestamp' => now()->toISOString(),
                    ]));
                }

                $localProcessedInChunk++;
                $currentErrorCount = Redis::llen("batch:{$batchId}:errors");

                // Incrementar contadores en Redis
                Redis::hincrby("batch:{$batchId}:progress", 'processed_records', 1);
                if (! empty($errors)) {
                    foreach ($errors as $error) {
                        $error['batch_id'] = $batchId;
                        Redis::rpush("batch:{$batchId}:errors", json_encode($error));
                        Redis::hincrby("batch:{$batchId}:progress", 'error_count', 1);
                    }
                } else {
                    Redis::rpush("batch:{$batchId}:staged_data", json_encode($formattedRow));
                }

                // Obtener valores acumulados
                $totalProcessedRecords = Redis::hget("batch:{$batchId}:progress", 'processed_records') ?? 0;
                $totalErrorCount = Redis::hget("batch:{$batchId}:progress", 'error_count') ?? 0;

                // Emitir evento por registro
                event(new ImportProgressEvent(
                    $batchId,
                    (int) $totalProcessedRecords,
                    'Procesando datos',
                    (int) $totalErrorCount,
                    'active',
                    $actualRowNumber
                ));
            }

            ProcessBatchService::incrementProcessedRecords($batchId, $processedRowsInChunk);

        } catch (Throwable $e) {
            Log::error("Error procesando fila {$actualRowNumber}: ".$e->getMessage(), [
                'batch_id' => $batchId,
                'row_data' => $row,
                'exception' => $e->getTraceAsString(),
            ]);
            Redis::rpush("batch:{$batchId}:errors", json_encode([
                'row_number' => $actualRowNumber,
                'column_name' => 'SYSTEM_ERROR',
                'error_message' => 'Error inesperado: '.$e->getMessage(),
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
                (int) $totalProcessedRecords,
                'Procesando datos',
                (int) $totalErrorCount,
                'active',
                $actualRowNumber
            ));
        }
    }

    private function readExcelChunk(): array
    {
        $import = new ChunkDataImport($this->startRow, $this->chunkSize);
        $data = Excel::toArray($import, $this->filePath)[0];

        return $data ?? [];
    }

    private function mapToAssociativeArray(array $row, array $headers): array
    {
        $formattedRow = [];
        foreach ($headers as $index => $header) {
            $formattedRow[$header] = $row[$index] ?? null;
        }

        return $formattedRow;
    }

    // Add a failed method for better error handling
    public function failed(Throwable $exception)
    {
        Log::error("ProcessDataChunkJob failed after {$this->tries} attempts", [
            'batch_id' => $this->batch()->id,
            'file_path' => $this->filePath,
            'start_row' => $this->startRow,
            'chunk_size' => $this->chunkSize,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
