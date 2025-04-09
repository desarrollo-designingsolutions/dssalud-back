<?php

namespace App\Http\Resources\InvoiceAudit;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceAuditPaginateInvoiceAuditResource extends JsonResource
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
            'count_patients' => $this->patients_count,
            'count_services' => $this->services_count,
            'total_value_services' => formatNumber($this->sumServicesTotalValue()),
            'glosas' => 0,
            'value_glosa' => 0,
            'spent' => 0,
        ];
    }
}
