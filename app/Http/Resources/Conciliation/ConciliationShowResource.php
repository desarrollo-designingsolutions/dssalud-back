<?php

namespace App\Http\Resources\Conciliation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConciliationShowResource extends JsonResource
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
            'company_id' => $this->company_id,
            'name' => $this->name,
            'third_id' => $this->third_id,
            'third_name' => $this->third?->name,
            'status_description' => $this->status?->description(),
        ];
    }
}
