<?php

namespace App\Http\Resources\User;

use App\Http\Resources\Third\ThirdSelectInfiniteResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserFormResource extends JsonResource
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
            'surname' => $this->surname,
            'email' => $this->email,
            'role_id' => $this->role_id,
            'company_id' => $this->company_id,
            'third_id' => new ThirdSelectInfiniteResource($this->third),
            'thirds_id' => ThirdSelectInfiniteResource::collection($this->thirds),
        ];
    }
}
