<?php

namespace App\Http\Resources\Schedule;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleAcceptFormResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $response_date = $this->scheduleable?->response_date ? Carbon::parse($this->scheduleable?->response_date)->format('Y-m-d H:i:s') : null;

        return [
            'id' => $this->id,
            'user_name' => $this->user?->full_name,
            'title' => $this->title,
            'start_date' => $this->start_date,
            'start_hour' => $this->start_hour,
            'end_date' => $this->end_date,
            'end_hour' => $this->end_hour,
            'description' => $this->description,
            'link' => $this->scheduleable->link,
            'response_status' => $this->scheduleable?->response_status?->value,
            'response_date' => $response_date,
        ];
    }
}
