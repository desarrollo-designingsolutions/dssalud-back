<?php

namespace App\Http\Resources\InvoiceAudit;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceAuditPaginatePatientResource extends JsonResource
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
            'identification_number' => $this->identification_number,
            'full_name' => $this->full_name,
            'gender' => $this->gender,
            'glosas' => 0,
            'value_glosa' => 0,
            'value_borrowed' => 0,
            'total_value' => 0,
            'status' => $this->assignmentStatusFor(['user_id' => $request->input('user_id')]),
        ];
    }
}
