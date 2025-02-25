<?php

namespace App\Http\Resources\SupportType;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportTypeSelectInfiniteResource extends JsonResource
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
            'title' => $this->code.' - '.$this->name,
            'code' => $this->code,
        ];
    }
}
