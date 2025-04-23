<?php

namespace App\Http\Resources\InvoiceAudit;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceAuditPaginateBatcheResource extends JsonResource
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
            'description' => $this->description,
            'count_invoice' => $this->count_invoice,
            'count_invoice_pending' => $this->count_invoice_pending,
            'count_invoice_completed' => $this->count_invoice_completed,
            'status' => $this->assignmentStatusFor(['user_id' => $request->input('user_id')]),
        ];
    }
}
