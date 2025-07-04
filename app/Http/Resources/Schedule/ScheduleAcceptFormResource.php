<?php

namespace App\Http\Resources\Schedule;

use App\Http\Resources\Third\ThirdSelectInfiniteResource;
use App\Http\Resources\TypeEvent\TypeEventSelectInfiniteResource;
use App\Http\Resources\User\UserSelectInfiniteResource;
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
        return [
            'id' => $this->id,
            'user_name' => $this->user?->full_name,
            'title' => $this->title,
            'start_date' => $this->start_date,
            'start_hour' => $this->start_hour,
            'end_date' => $this->end_date,
            'end_hour' => $this->end_hour,
            'description' => $this->description,
            'link' => $this->link,
            'response_status' => $this->response_status?->value,
            'response_date' => $this->response_date,
        ];
    }
}
