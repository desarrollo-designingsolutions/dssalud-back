<?php

namespace App\Jobs\Rips;

use App\Enums\StatusRipsEnum;
use App\Jobs\ProcessSendEmail;
use App\Models\Rip;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessValidationZip implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $ripId;

    public $userData;

    public $company_id;

    /**
     * Create a new job instance.
     */
    public function __construct($ripId, $userData, $company_id)
    {
        $this->ripId = $ripId;
        $this->userData = $userData;
        $this->company_id = $company_id;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {

        //busco el registro rips
        $rip = Rip::find($this->ripId);

        $errorMessages = [];

        //validamos los archivos del zip
        $infoValidationZip = validationFileZip($rip, $errorMessages);

        $infoValidation = [
            'infoValidationZip' => $infoValidationZip,
            'errorMessages' => $errorMessages,
        ];

        //si el archivo zip de los txt no cumple con las condiciones necesarias
        if (count($errorMessages) > 0) {

            //actualizo la informacion de la validacion excel en el registro
            $rip->validationZip = json_encode($infoValidation);
            $rip->status = StatusRipsEnum::ERROR_ZIP;
            $rip->save();

            //eliminamos el archivo zip subido
            deletefileZipData($rip);
        } else {
            if (is_bool($infoValidationZip) && $infoValidationZip == true) {

                //abrimos el zip y extraigos sus archivos
                $files = openFileZip($rip->path_zip, $this->company_id);

                //se contruye un array con toda la data de los txt unida
                $build = buildAllDataTogether($files);

                //genero los consecutivos para usuarios y servicios tomando encuenta que deben ser consecutivos e iniciar en uno en los servicios y en usuarios
                generateConsecutive($build['data']);

                //valida que las facturas a procesar si sean realmente de la empresa, comparandolas con los nits de la empresa registrado
                $resultValidateNitsAndInvoice = validateNitsAndInvoice($build['data'], $rip->id, $this->company_id);

                if (! $resultValidateNitsAndInvoice) {
                    $partitions = array_chunk($build['data'], env('CHUNKSIZE', 10));

                    $lastIndex = count($partitions) - 1; // Índice del último elemento

                    foreach ($partitions as $key => $value) {

                        // Determina si es el último elemento
                        $isLast = $key === $lastIndex;

                        // Envía true si es el último elemento, de lo contrario, envía false
                        ProcessValidationTxt::dispatch($rip->id, $value, $this->userData, $isLast);
                    }
                }
            }
        }

        if ($this->userData) {
            ProcessSendEmail::dispatch($this->userData->email, 'Mails.Rips.ValidationZip', 'Información Validaciones ZIP', [
                'infoValidation' => $infoValidation,
            ]);
        }
    }
}
