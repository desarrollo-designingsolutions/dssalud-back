<?php

namespace App\Http\Resources\ProcessBatchesError;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProcessBatchesErrorPaginateResource extends JsonResource
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
            'row_number' => $this->row_number,
            'column_name' => $this->column_name,
            'error_message' => $this->error_message,
            'error_type' => $this->error_type,
            'error_value' => $this->error_value,
        ];
    }
}
