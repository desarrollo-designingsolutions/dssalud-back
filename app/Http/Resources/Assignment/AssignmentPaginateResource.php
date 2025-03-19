<?php

namespace App\Http\Resources\Assignment;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssignmentPaginateResource extends JsonResource
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
            'count_invoice_assignment' => $this->assigned_invoice_audits_count,
            'count_invoice_pending' => 0,
            'finish' => 0,
            'values' => $this->sumInvoiceAuditsTotalValue(),
        ];
    }
}
