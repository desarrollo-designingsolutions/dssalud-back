<?php

namespace App\Http\Resources\InvoiceAudit;

use App\Enums\Assignment\StatusAssignmentEnum;
use Carbon\Carbon;
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
            'count_invoice_assignment' => $this->countInvoiceByStatus(StatusAssignmentEnum::ASSIGNMENT_EST_002->value),
            'count_invoice_pending' => $this->countInvoiceByStatus(StatusAssignmentEnum::ASSIGNMENT_EST_001->value),
            'count_invoice_finish' => $this->countInvoiceByStatus(StatusAssignmentEnum::ASSIGNMENT_EST_003->value),
            'values' => $this->sumInvoiceAuditsTotalValue(),
        ];
    }
}
