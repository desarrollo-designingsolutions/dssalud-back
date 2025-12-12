<?php

namespace App\Console\Commands;

use App\Events\ImportProgressEvent;
use App\Imports\ConciliationImport\Jobs\ProcessCsvImportJob;
use App\Imports\ConciliationImport\Traits\ImportHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use SplFileObject;

class CustomersImportCommand extends Command
{
    use ImportHelper;

    protected $signature = 'import:customers {filePath}';

    protected $description = 'Import customers from CSV file using queues.';

    public function handle(): void
    {
        $filePath = $this->argument('filePath');

        if (! file_exists($filePath)) {
            $this->error("El archivo CSV no existe en la ruta: {$filePath}");

            return;
        }

        $this->currentBatchId = (string) Str::uuid();
        $fileName = basename($filePath);
        $fileSize = filesize($filePath);

        $totalRows = 0;
        $file = new SplFileObject($filePath, 'r');
        $file->setFlags(SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD);
        $file->fgets(); // Skip header
        foreach ($file as $line) {
            $totalRows++;
        }
        $file = null;

        // Almacenar metadatos iniciales del batch en Redis para que el evento los lea
        // CORRECCIÓN: Usar Redis::hmset() para establecer múltiples campos de un hash
        Redis::hmset("batch:{$this->currentBatchId}:metadata", [
            'total_rows' => (string) $totalRows,
            'file_name' => (string) $fileName,
            'file_size' => (string) $fileSize,
            'status' => 'queued',
            'started_at' => 'N/A',
            'completed_at' => 'N/A',
            'current_sheet' => (string) 1,
            'total_sheets' => (string) 1,
        ]);
        Redis::expire("batch:{$this->currentBatchId}:metadata", 3600 * 24);

        $this->info("Iniciando importación para batch ID: {$this->currentBatchId}");
        $this->info("Archivo: {$fileName}, Filas: {$totalRows}");

        ProcessCsvImportJob::dispatch($filePath, $this->currentBatchId, $totalRows);

        ImportProgressEvent::dispatch(
            $this->currentBatchId,
            (string) 0,
            'Archivo encolado para procesamiento',
            (string) 0,
            'queued',
            '0'
        );

        $this->info('Job de importación despachado a la cola.');
    }
}
