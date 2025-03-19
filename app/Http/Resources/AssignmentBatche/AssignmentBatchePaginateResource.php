<?php

namespace App\Http\Resources\AssignmentBatche;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssignmentBatchePaginateResource extends JsonResource
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
            'description' => $this->description,
            'assignment_date' => count($this->assignments),
            'pending_date' => 0,
            'completed_date' => 0,
        ];
    }
}
