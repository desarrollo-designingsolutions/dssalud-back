<?php

namespace App\Http\Resources\InvoiceAudit;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceAuditPaginateThirdsResource extends JsonResource
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
            'nit' => $this->nit,
            'name' => $this->name,
            'count_invoice_total' => $this->count_invoice_total,
            'count_invoice_pending' => $this->count_invoice_pending,
            'count_invoice_finish' => $this->count_invoice_finish,
            'values' => formatNumber($this->total_value_sum),
            'status' => $this->assignmentStatusFor(['user_id' => $request->input('user_id')]),
        ];
    }
}
