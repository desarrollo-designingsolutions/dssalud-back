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

    public $timeout = 1800;

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
                    } else {
                        Redis::rpush("batch:{$batchId}:staged_data", json_encode($formattedRow));
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

                event(new ImportProgressEvent(
                    $batchId,
                    $initialProcessedRecordsForBatch + $localProcessedInChunk,
                    'Procesando datos',
                    $currentErrorCount,
                    'active',
                    $actualRowNumber
                ));
            }

            ProcessBatchService::incrementProcessedRecords($batchId, $processedRowsInChunk);

        } catch (Throwable $e) {
            Log::error("Error crítico en ProcessDataChunkJob (filas {$this->startRow}-".
                ($this->startRow + $this->chunkSize - 1).'): '.$e->getMessage());
            Redis::rpush("batch:{$batchId}:errors", json_encode([
                'row_number' => $this->startRow,
                'column_name' => 'SYSTEM_ERROR',
                'error_message' => 'Error crítico en chunk: '.$e->getMessage(),
                'error_type' => 'system_chunk_error',
                'original_data' => null,
                'timestamp' => now()->toISOString(),
            ]));
            throw $e;
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
}
