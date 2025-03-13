<?php

namespace App\Http\Resources\Company;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyFormResource extends JsonResource
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
            'nit' => $this->nit,
            'phone' => $this->phone,
            'country_id' => [
                'value' => $this->country_id,
                'title' => $this->country?->name,
            ],
            'state_id' => $this->state_id,
            'city_id' => $this->city_id,
            'address' => $this->address,
            'email' => $this->email,
            'start_date' => $this->created_at->format('Y-m-d'),
            'final_date' => $this->final_date,
            'logo' => $this->logo,
        ];
    }
}
