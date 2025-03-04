<?php

namespace App\Jobs\Rips;

use App\Models\Invoice;
use App\Models\Rip;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessValidationExcel implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $data;

    public $userData;

    /**
     * Create a new job instance.
     */
    public function __construct($data, $userData = null)
    {
        $this->data = $data;
        $this->userData = $userData;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //si algun "valor" del excel esta vacio lo guardo en el array de errores
        // $errorMessages = validateNullExcel($this->data['xlsCollection']);

        // Agrupamos los registros por num_factura
        $groupedData = groupByNumFactura($this->data['xlsCollection']);

        //validamos la data de los xls
        // $validateGroupedXlsData = validateGroupedData($groupedData); //preguntar si se termina o se borra
        // $num_invoices = $groupedData->keys();

        //busco el registro rips
        $rip = $model = Rip::find($this->data['rip_id']);

        // si llega invoice_id
        $invoice_id = $this->data['invoice_id'];

        if (isset($invoice_id) && ! empty($invoice_id) && $invoice_id != 'null') {
            // si llega invoice_id tomamos el json del invoice
            $model = Invoice::find($this->data['invoice_id']);
            $path = $model->path_json;
            //abirmos el json y lo pasamos a un array
            $jsonData = openFileJson($path);
            $jsonData = [$jsonData]; // aqui lo hacemos asi para que me siga funcionando la funcion proccessData cunado es independiente
        } else {
            //si llega rip_id pero no invoice_id tomamos el json del rip
            $path = $model->path;
            //abirmos el json y lo pasamos a un array
            $jsonData = openFileJson($path);
        }

        //aqui pasamos toda la data encontrada en el archivo xls al array general de las facturas
        $jsonInfo = processData($jsonData, $groupedData);

        //informacion del resultado de las validaciones Excel
        $infoValidation = validateDataFilesExcel($jsonInfo, $jsonData);

        //esto es para verificar que a las facturas o factura no le falte un campo null de los configurados
        //si las facturas estan sin los campos vacios estonces actualiza la factura y el rips
        // validateNullFileJsonToExcel($rip->id, $jsonData);

        //actualizo la informacion de la validacion excel en el registro
        if (count($infoValidation['errorMessages']) == 0) {
            $rip->validationExcel = null;

            $errorMessagesXmlAllInovices = [];

            foreach ($rip->invoices as $key => $value) {
                if ($value->validationXml) {
                    $validationXml = json_decode($value->validationXml, 1);

                    $errorMessagesXmlAllInovices = array_merge($errorMessagesXmlAllInovices, $validationXml['errorMessages']);
                }
            }
        } else {
            $rip->validationExcel = json_encode($infoValidation);
        }

        $rip->save();

        //Aqui se traspasa la informacion que esta bien segun las validaciones de excel
        $jsonInvoices = $jsonData;
        foreach ($jsonInvoices as $key => $value) {
            DB::beginTransaction();
            //se guarda el xls nuevo y json independientes en la bd
            saveReloadDataInvoice($rip->id, $value, count($infoValidation['errorMessages']));

            DB::commit();
        }

        generateDataJsonAndExcel($rip->id);
        validateRipsStatus($rip->id);

        if ($this->userData) {
            ProcessSendEmail::dispatch($this->userData->email, 'Mails.Rips.ValidationExcel', 'Información Validaciones EXCEL', [
                'infoValidation' => $infoValidation,
            ]);
        }
    }
}
