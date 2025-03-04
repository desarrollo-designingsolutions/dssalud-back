<?php

namespace App\Http\Resources\Rip;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RipManualListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $errorMessagesXmlAllInovices = [];

        if(count($this->invoices)>0){
            foreach ($this->invoices as $key => $value) {
                if ($value->validationXml) {
                    $validationXml = json_decode($value->validationXml, 1);
                    $errorMessagesXmlAllInovices = array_merge($errorMessagesXmlAllInovices, $validationXml['errorMessages']);
                }
            }
        }


        $evalue = evaluateCompleteManualRips($this->path);


        return [
            'id' => $this->id,
            'user_name' => $this->user?->name,
            'numeration' => $this->numeration,
            'numInvoices' => $this->numInvoices,
            'sumVr' => $this->sumVr,
            'send_status_id' => $this->send_status_id,
            'send_status_name' => $this->status_send->name,
            'status_id' => $this->status_id,
            'status_name' => $this->status->name,
            'created_at' => $this->created_at->format('d-m-Y H:i'),
            'fileJson' => env('SYSTEM_URL_BACK') . 'storage/' . $this->path,
            'fileXls' => env('SYSTEM_URL_BACK') . 'storage/' . $this->xls,
            'successfulInvoices' => $evalue["count_full"],
            'failedInvoices' => $evalue["count_notFull"],
            'validationZip' => $this->validationZip,
            'validationTxt' => $this->validationTxt,
            'validationExcel' => $this->validationExcel,
            'validationXml' => json_encode(['errorMessages' => $errorMessagesXmlAllInovices]),
        ];
    }
}
