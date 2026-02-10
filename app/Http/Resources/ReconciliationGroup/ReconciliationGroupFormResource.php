<?php

namespace App\Http\Resources\ReconciliationGroup;

use App\Http\Resources\Third\ThirdSelectInfiniteResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReconciliationGroupFormResource extends JsonResource
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
            'third_id' => new ThirdSelectInfiniteResource($this->third),
        ];
    }
}
