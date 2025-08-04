<?php

namespace App\Http\Resources\ReconciliationGroup;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReconciliationGroupPaginateResource extends JsonResource
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
            'name' => $this->name,
            'third_nit' => $this->third?->nit,
            'third_name' => $this->third?->name,
            'link' => env('SYSTEM_URL_BACK').'reconciliationGroup/index/'.$this->id,
        ];
    }
}
