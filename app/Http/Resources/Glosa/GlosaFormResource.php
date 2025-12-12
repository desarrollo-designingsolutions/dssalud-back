<?php

namespace App\Http\Resources\Glosa;

use App\Http\Resources\CodeGlosa\CodeGlosaSelectInfiniteResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GlosaFormResource extends JsonResource
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
            'user_id' => $this->user_id,
            'service_id' => $this->service_id,
            'code_glosa_id' => new CodeGlosaSelectInfiniteResource($this->code_glosa),
            'glosa_value' => $this->glosa_value,
            'observation' => $this->observation,
        ];
    }
}
