<?php

namespace App\Http\Resources\CodeGlosa;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CodeGlosaSelectInfiniteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'value' => $this->id,
            'title' => $this->code.' - '.$this->description,
            'code' => $this->code,
        ];
    }
}
