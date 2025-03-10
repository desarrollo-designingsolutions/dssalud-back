<?php

namespace App\Http\Resources\Filing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FilingPaginateResource extends JsonResource
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
            'contract_name' => $this->contract?->name,
            'type' => $this->type->description(),
            'status' => $this->status,
            'sumVr' => formatNumber($this->sumVr),
            'filing_invoice_pre_radicated_count' => $this->filing_invoice_pre_radicated_count,
            'status_backgroundColor' => $this->status->backgroundColor(),
            'status_description' => $this->status->description(),
        ];
    }
}
