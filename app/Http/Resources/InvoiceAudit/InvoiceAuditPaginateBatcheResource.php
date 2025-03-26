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
            'count_invoice' => $this->countInvoiceByFilter(['user_id' => $this->assignments->first()->user_id]),
            'count_invoice_pending' => $this->countInvoiceByFilter(['user_id' => $this->assignments->first()->user_id,'status' => StatusAssignmentEnum::ASSIGNMENT_EST_002->value]),
            'count_invoice_completed' => $this->countInvoiceByFilter(['user_id' => $this->assignments->first()->user_id,'status' => StatusAssignmentEnum::ASSIGNMENT_EST_003->value]),
        ];
    }
}
