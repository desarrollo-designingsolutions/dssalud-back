<?php

namespace App\Http\Resources\Glosa;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GlosaPaginateResource extends JsonResource
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
            'user_full_name' => $this->user?->full_name,
            'service_description' => $this->service?->description,
            'code_glosa_description' => $this->code_glosa?->description,
            'glosa_value' => formatNumber($this->glosa_value),
            'observation' => $this->observation,
        ];
    }
}
