<?php

namespace App\Http\Resources\InvoiceAudit;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceAuditPaginateServiceResource extends JsonResource
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
            'type' => '',
            'nap' => '',
            'detail_code' => $this->detail_code,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_value' => formatNumber($this->unit_value),
            'moderator_value' => '',
            'total_value' => formatNumber($this->total_value),
            'total_value_origin' => $this->total_value,
        ];
    }
}
