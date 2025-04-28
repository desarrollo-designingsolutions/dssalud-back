<?php

namespace App\Http\Resources\Assignment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssignmentPaginateThirdsResource extends JsonResource
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
            'count_invoice_total' => $this->count_invoice_total,
            'count_invoice_pending' => $this->count_invoice_pending,
            'count_invoice_finish' => $this->count_invoice_finish,
            'total_value_sum' => formatNumber($this->total_value_sum),
            'status' => $this->assignmentStatusFor([]),
            'count_users' => $this->count_users,
            'user_names' => $this->user_names,
        ];
    }
}
