<?php

namespace App\Http\Resources\InvoiceAudit;

use App\Enums\Assignment\StatusAssignmentEnum;
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
            'count_invoice_assignment' => $this->countInvoiceByStatus(StatusAssignmentEnum::ASSIGNMENT_EST_002->value),
            'count_invoice_pending' => $this->countInvoiceByStatus(StatusAssignmentEnum::ASSIGNMENT_EST_001->value),
            'count_invoice_completed' => $this->countInvoiceByStatus(StatusAssignmentEnum::ASSIGNMENT_EST_003->value),
        ];
    }
}
