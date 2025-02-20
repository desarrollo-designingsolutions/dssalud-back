<?php

namespace App\Jobs\Filing;

use App\Enums\Filing\StatusFilingEnum;
use App\Events\FilingProgressEvent;
use App\Jobs\Filing\ProcessFilingValidationTxt;
use App\Models\Filing;
use App\Services\Redis\TemporaryFilingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessFilingValidationZip implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $filing_id;

    public $userData;

    public $company_id;

    /**
     * Create a new job instance.
     */
    public function __construct($filing_id, $userData, $company_id)
    {
        $this->filing_id = $filing_id;
        $this->userData = $userData;
        $this->company_id = $company_id;
    }

    /**
     * Execute the job.
     */
    public function handle(TemporaryFilingService $tempFilingService): void
    {
        //busco el registro
        $filing = Filing::find($this->filing_id);

        $errorMessages = [];

        //validamos los archivos del zip
        $infoValidationZip = validationFileZip($filing, $errorMessages);

        $infoValidation = [
            'infoValidationZip' => $infoValidationZip,
            'errorMessages' => $errorMessages,
        ];

        //si el archivo zip de los txt no cumple con las condiciones necesarias
        if (count($errorMessages) > 0) {

            //actualizo la informacion de la validacion excel en el registro
            $filing->validationZip = json_encode($infoValidation);
            $filing->status = StatusFilingEnum::ERROR_ZIP;
            $filing->save();

            //eliminamos el archivo zip subido
            deletefileZipData($filing);
        } else {
            if (is_bool($infoValidationZip) && $infoValidationZip == true) {

                //abrimos el zip y extraigos sus archivos
                $files = openFileZip($filing->path_zip, $this->company_id);

                //se contruye un array con toda la data de los txt unida
                $build = buildAllDataTogether($files);

                //actualizo mi data temporal
                $tempFilingService->addToTemporaryData($filing->id, "invoices", $build['data']);

                $partitions = array_chunk($build['data'], env('CHUNKSIZE', 10));

                $lastIndex = count($partitions) - 1; // Índice del último elemento

                $totalPartitions = count($partitions);
                $currentProgress = 0;

                foreach ($partitions as $key => $value) {

                    // Determina si es el último elemento
                    $isLast = $key === $lastIndex;

                    // Envía true si es el último elemento, de lo contrario, envía false
                    ProcessFilingValidationTxt::dispatch($filing->id, $value, $this->userData, $isLast);

                    // Calculamos el progreso
                    $currentProgress = (($key + 1) / $totalPartitions) * 100;

                    // Emitimos un evento con el progreso actual
                    FilingProgressEvent::dispatch($filing->id, $currentProgress);
                }
            }
        }
    }
}
