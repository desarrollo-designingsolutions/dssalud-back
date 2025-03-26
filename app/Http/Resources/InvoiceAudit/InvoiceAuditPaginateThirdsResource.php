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
            'count_invoice_assignment' => $this->countInvoiceByFilter(),
            'count_invoice_pending' => $this->countInvoiceByFilter(['status' => StatusAssignmentEnum::ASSIGNMENT_EST_002->value]),
            'count_invoice_finish' => $this->countInvoiceByFilter(['status' => StatusAssignmentEnum::ASSIGNMENT_EST_003->value]),
            'values' => formatNumber($this->sumInvoiceAuditsTotalValue()),
        ];
    }
}
