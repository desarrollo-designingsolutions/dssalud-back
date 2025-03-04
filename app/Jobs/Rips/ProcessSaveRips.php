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

class ProcessSaveRips implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $ripId;

    public $userData;

    /**
     * Create a new job instance.
     */
    public function __construct($ripId, $userData = null)
    {
        $this->ripId = $ripId;
        $this->userData = $userData;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //busco el registro rips
        $rip = Rip::select('id', 'validationTxt', 'status', 'numInvoices', 'failedInvoices')->find($this->ripId);

        //Obtengo las facturas positivas
        $validationTxt = json_decode($rip->validationTxt, 1);

        $arraySuccessfulInvoices = $validationTxt['jsonSuccessfullInvoices'];

        //guardo las facturas positivas y genero las exceles y jsons independientes
        saveReloadDataRips([
            'ripId' => $this->ripId,
            'arraySuccessfulInvoices' => $arraySuccessfulInvoices,
        ]);

        //genero el excel y json global
        generateDataJsonAndExcel($this->ripId);

        //busco el registro rips
        $rip = Rip::find($this->ripId);

        //busco la informacion
        $validationTxt = json_decode($rip['validationTxt'], 1);

        //cambio el estado del rips a "Incomplete"
        $rip->status = StatusRipsEnum::INCOMPLETE;
        $rip->numInvoices = $validationTxt['totalSuccessfulInvoices'];
        $rip->failedInvoices = $validationTxt['totalSuccessfulInvoices'];
        $rip->save();

        if ($this->userData) {
            ProcessSendEmail::dispatch($this->userData->email, 'Mails.Rips.ValidationTxt', 'Información fin registro Rips '.$rip->id, [
                'infoValidation' => $validationTxt,
            ]);
        }
    }
}
