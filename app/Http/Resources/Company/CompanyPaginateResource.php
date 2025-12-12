<?php

namespace App\Http\Resources\Company;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyPaginateResource extends JsonResource
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
            'logo' => $this->logo,
            'name' => $this->name,
            'nit' => $this->nit,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
        ];
    }
}
