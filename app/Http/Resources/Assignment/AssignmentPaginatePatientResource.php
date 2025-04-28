<?php

namespace App\Http\Resources\Assignment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssignmentPaginatePatientResource extends JsonResource
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
            'glosas' => $this->count_glosas,
            'value_glosa' => formatNumber($this->value_glosa),
            'value_approved' => formatNumber($this->value_approved),
            'total_value' => formatNumber($this->total_value),
            'status' => $this->assignmentStatusFor([]),
        ];
    }
}
