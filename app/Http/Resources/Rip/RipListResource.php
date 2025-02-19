<?php

namespace App\Http\Resources\Rip;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RipListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $errorMessagesXmlAllInovices = [];

        foreach ($this->invoices as $key => $value) {
            if ($value->validationXml) {
                $validationXml = json_decode($value->validationXml, 1);
                $errorMessagesXmlAllInovices = array_merge($errorMessagesXmlAllInovices, $validationXml['errorMessages']);
            }
        }


        $path_json = $this->path_json ?  env('SYSTEM_URL_BACK') . 'storage/' . $this->path_json : null;
        $path_xls = $this->path_xls ? env('SYSTEM_URL_BACK') . 'storage/' . $this->path_xls : null;

        return [
            'id' => $this->id,
            'user_name' => $this->user?->name,
            'numeration' => $this->numeration,
            'numInvoices' => $this->numInvoices,
            'sumVr' => $this->sumVr,
            'send' => $this->send,

            'status' => $this->status,
            'status_description' => $this->status->description(),
            'status_backgroundColor' => $this->status->backgroundColor(),

            'created_at' => $this->created_at->format('d-m-Y H:i'),
            'path_json' => $path_json,
            'path_xls' => $path_xls,
            'successfulInvoices' => $this->successfulInvoices,
            'failedInvoices' => $this->failedInvoices,

            'view_btn_error' => $this->view_btn_error,
        ];
    }
}
