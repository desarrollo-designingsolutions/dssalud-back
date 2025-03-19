<?php

namespace App\Jobs\Filing;

use App\Helpers\Common\ErrorCollector;
use App\Helpers\Constants;
use App\Helpers\FilingOld\ZipHelper;
use App\Models\Filing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class ProcessFilingValidation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $filing_id;

    /**
     * Create a new job instance.
     */
    public function __construct($filing_id)
    {
        $this->filing_id = $filing_id;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Busco el registro
        $filing = Filing::find($this->filing_id);

        $keyErrorRedis = "filingOld:{$this->filing_id}:errors";

        $files = ZipHelper::openFileZip($filing->id, $filing->path_zip);
        $errorMessages = ErrorCollector::getErrors($keyErrorRedis);

        // Calcular el total global de filas
        $totalRows = collect($files)->sum('count_rows');

        // Almacenar en Redis
        Redis::set("filingOld:{$this->filing_id}:total_rows", $totalRows);
        Redis::set("filingOld:{$this->filing_id}:processed_rows", 0);
        Redis::set("filingOld:{$this->filing_id}:validationCt_codigoArchivos", json_encode([]));
        Redis::set("filingOld:{$this->filing_id}:files_txts", json_encode($files));

        // Procesar cada archivo y despachar sub-jobs por chunk
        foreach ($files as $file) {

            $prefix_name = strtoupper(substr(basename($file['name']), 0, 2));

            Redis::set("filingOld:{$this->filing_id}:{$prefix_name}", json_encode($file['contentDataArray']));

            $chunkSize = Constants::CHUNKSIZE;

            $chunks = array_chunk($file['contentDataArray'], $chunkSize);

            foreach ($chunks as $index => $chunk) {
                // Calcular el índice inicial de este chunk en el archivo original
                $startRow = ($index * $chunkSize) + 1; // +1 porque las filas suelen empezar en 1, no en 0

                // Despachar el job con el índice inicial
                ProcessChunkJob::dispatch($this->filing_id, $file['name'], $chunk, $startRow);
            }
        }
    }
}
