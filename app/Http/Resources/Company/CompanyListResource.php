<?php

namespace App\Http\Resources\Company;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        $start_date = $this->created_at ? $this->created_at->format('Y-m-d') : null;
        $final_date = $this->final_date ? $this->final_date->format('Y-m-d') : null;
        $diffInDays = $this->start_date && $this->final_date ? $this->final_date->diffInDays($this->start_date) : 0;

        return [
            'id' => $this->id,
            'logo' => $this->logo,

            'name' => $this->name,
            'nit' => $this->nit,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'start_date' => $start_date,
            'final_date' => $final_date,
            'remaining_days' => $diffInDays,
        ];
    }
}
