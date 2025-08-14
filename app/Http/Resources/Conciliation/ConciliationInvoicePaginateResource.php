<?php

namespace App\Http\Resources\Conciliation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConciliationInvoicePaginateResource extends JsonResource
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
            'invoice_number' => $this->invoiceAudit?->invoice_number,
            'total_value' => formatNumber($this->invoiceAudit?->total_value),
            'origin' => $this->invoiceAudit?->origin,
            'modality' => $this->invoiceAudit?->modality,
            'contract_number' => $this->invoiceAudit?->contract_number,
            'status_description' => $this->conciliation_invoice?->status->description(),
            'status_backgroundColor' => $this->conciliation_invoice?->status->backgroundColor(),
        ];
    }
}
