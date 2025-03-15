<?php

namespace App\Jobs\Filing;

use App\Enums\Filing\StatusFilingEnum;
use App\Events\FilingFinishProcessJob;
use App\Events\FilingProgressEvent;
use App\Helpers\FilingOld\ValidationOrchestrator;
use App\Models\Filing;
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
    public function handle(): void
    {
        // busco el registro
        $filing = Filing::find($this->filing_id);

        // //validamos los archivos del zip
        $errorMessages = ValidationOrchestrator::validate($this->filing_id, $filing->path_zip);

        // $infoValidation = [
        //     'infoValidationZip' => count($errorMessages) > 0 ? true : false,
        //     'errorMessages' => $errorMessages,
        // ];

        // si el archivo zip de los txt no cumple con las condiciones necesarias
        if (count($errorMessages) > 0) {

            // actualizo la informacion de la validacion zip en el registro
            $filing->validationZip = json_encode($errorMessages);
            $filing->status = StatusFilingEnum::FILING_EST_006;
            $filing->save();

            // Emitimos un evento con el progreso actual
            FilingProgressEvent::dispatch($filing->id, 100);

            FilingFinishProcessJob::dispatch($filing->id);
        } else {

            ProcessFilingValidation::dispatch($this->filing_id);

        }
    }
}
