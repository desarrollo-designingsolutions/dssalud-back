<?php

namespace App\Http\Resources\Assignment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssignmentPaginateInvoiceAuditResource extends JsonResource
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
            'glosas' => 0,
            'value_glosa' => 0,
            'spent' => 0,
            'status' => $this->assignmentStatusFor([]),
            'user_names' => $this->user_names,

        ];
    }
}
