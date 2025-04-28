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
            'total_value' => formatNumber($this->total_value),
            'glosas' => $this->count_glosas,
            'value_glosa' => formatNumber($this->value_glosa),
            'value_approved' => formatNumber($this->value_approved),
            'status' => $this->status,
            'user_names' => $this->user_names,
        ];
    }
}
