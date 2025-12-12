<?php

namespace App\Jobs\Filing;

use App\Enums\Filing\StatusFilingEnum;
use App\Events\FilingFinishProcessJob;
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
use Illuminate\Support\Facades\Storage;

class ProcessSaveFiling implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $filingId;

    public $userData;

    /**
     * Create a new job instance.
     */
    public function __construct($filingId, $userData = null)
    {
        $this->filingId = $filingId;
        $this->userData = $userData;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Obtengo los errores de este proceso
        $keyErrorRedis = "filingOld:{$this->filingId}:errors";
        $errorCollector = ErrorCollector::getErrors($keyErrorRedis);

        // busco el registro
        $filing = Filing::find($this->filingId);

        if (count($errorCollector) > 0) {
            $status = StatusFilingEnum::FILING_EST_007;
            $validationTxt = json_encode($errorCollector);
            $path_json = null;
        } else {
            $status = StatusFilingEnum::FILING_EST_008;
            $validationTxt = null;

            $tempDirectory = json_decode(Redis::get("filingOld:{$this->filingId}:files_txts"), 1);
            $jsonContents = ZipHelper::buildAllDataTogether($tempDirectory);

            // JSONS
            // Nombre del archivo json
            $nameFile = 'filing_'.$this->filingId.'.json';
            // Guarda el JSON en el sistema de archivos usando el disco predeterminado (puede configurar otros discos si es necesario)
            $ruta = 'companies/company_'.$filing->company_id.'/filings/'.$filing->type->value.'/filing_'.$this->filingId.'/'.$nameFile; // Ruta donde se guardará la carpeta
            Storage::disk(Constants::DISK_FILES)->put($ruta, json_encode($jsonContents)); // guardo el archivo
            $path_json = $ruta;
        }

        $filing->status = $status;
        $filing->validationTxt = $validationTxt;
        $filing->path_json = $path_json;
        $filing->save();

        Redis::del("filingOld:{$this->filingId}*");

        FilingFinishProcessJob::dispatch($filing->id);
    }
}
