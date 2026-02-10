<?php

namespace App\Http\Resources\FilingInvoice;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class FilingInvoiceThirdsPaginateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'users_count' => $this->users_count,
            'files_count' => $this->files_count,
            'case_number' => $this->case_number,
            'date' => Carbon::parse($this->date)->format('d-m-Y H:s'),
            'sumVr' => formatNumber($this->sumVr),

            'status' => $this->status,
            'status_backgroundColor' => $this->status->backgroundColor(),
            'status_description' => $this->status->description(),

            'status_xml' => $this->status_xml,
            'status_xml_backgroundColor' => $this->status_xml->backgroundColor(),
            'status_xml_description' => $this->status_xml->description(),

            'path_xml' => $this->path_xml,

            'third_name' => $this->contract?->third?->name,
            'created_at' => Carbon::parse($this->created_at)->format('d-m-Y'),
            'updated_at' => Carbon::parse($this->updated_at)->format('d-m-Y'),
        ];
    }
}