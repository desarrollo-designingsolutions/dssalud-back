<?php

namespace App\Jobs\Filing;

use App\Events\ProgressCircular;
use App\Helpers\FilingOld\ACFileValidator;
use App\Helpers\FilingOld\AFFileValidator;
use App\Helpers\FilingOld\AHFileValidator;
use App\Helpers\FilingOld\AMFileValidator;
use App\Helpers\FilingOld\ANFileValidator;
use App\Helpers\FilingOld\APFileValidator;
use App\Helpers\FilingOld\ATFileValidator;
use App\Helpers\FilingOld\AUFileValidator;
use App\Helpers\FilingOld\CTFileValidator;
use App\Helpers\FilingOld\USFileValidator;
use App\Models\Filing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class ProcessChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filing_id;

    protected $file_name;

    protected $chunk;

    protected $start_row;

    public function __construct($filing_id, $file_name, $chunk, $start_row)
    {
        $this->filing_id = $filing_id;
        $this->file_name = $file_name;
        $this->chunk = $chunk;
        $this->start_row = $start_row;
    }

    public function handle(): void
    {
        // sleep(3);

        // $dataExtra = json_decode(Redis::get("filingOld:{$this->filing_id}:{$this->file_name}"), 1);

        // Procesar cada registro en el chunk y determinar su número de fila
        foreach ($this->chunk as $offset => $row) {
            $rowNumber = $this->start_row + $offset; // Número de fila en el archivo original

            // Validar según el tipo de archivo
            if (strpos($this->file_name, 'CT') !== false) {
                CTFileValidator::validate($this->file_name, $row, $rowNumber, $this->filing_id);
            }
            if (strpos($this->file_name, 'US') !== false) {
                USFileValidator::validate($this->file_name, $row, $rowNumber, $this->filing_id);
            }
            if (strpos($this->file_name, 'AF') !== false) {
                AFFileValidator::validate($this->file_name, $row, $rowNumber, $this->filing_id);
            }
            if (strpos($this->file_name, 'AC') !== false) {
                ACFileValidator::validate($this->file_name, $row, $rowNumber, $this->filing_id);
            }
            if (strpos($this->file_name, needle: 'AP') !== false) {
                APFileValidator::validate($this->file_name, $row, $rowNumber, $this->filing_id);
            }
            if (strpos($this->file_name, needle: 'AU') !== false) {
                AUFileValidator::validate($this->file_name, $row, $rowNumber, $this->filing_id);
            }
            if (strpos($this->file_name, needle: 'AH') !== false) {
                AHFileValidator::validate($this->file_name, $row, $rowNumber, $this->filing_id);
            }
            if (strpos($this->file_name, needle: 'AN') !== false) {
                ANFileValidator::validate($this->file_name, $row, $rowNumber, $this->filing_id);
            }
            if (strpos($this->file_name, needle: 'AM') !== false) {
                AMFileValidator::validate($this->file_name, $row, $rowNumber, $this->filing_id);
            }
            if (strpos($this->file_name, needle: 'AT') !== false) {
                ATFileValidator::validate($this->file_name, $row, $rowNumber, $this->filing_id);
            }
        }

        // Actualizar el contador de filas procesadas en Redis
        $processed = count($this->chunk);
        Redis::incrby("filingOld:{$this->filing_id}:processed_rows", $processed);

        // Calcular el porcentaje global
        $totalRows = Redis::get("filingOld:{$this->filing_id}:total_rows");
        $processedRows = Redis::get("filingOld:{$this->filing_id}:processed_rows");
        $percentage = ($processedRows / $totalRows) * 100;

        // Despachar evento para el frontend
        ProgressCircular::dispatch("filing.{$this->filing_id}", $percentage);

        if ($percentage == 100) {

            ProcessSaveFiling::dispatch($this->filing_id);

        }
    }
}
