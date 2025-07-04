<?php

namespace App\Http\Resources\Schedule;

use App\Http\Resources\Third\ThirdSelectInfiniteResource;
use App\Http\Resources\TypeEvent\TypeEventSelectInfiniteResource;
use App\Http\Resources\User\UserSelectInfiniteResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleFormResource extends JsonResource
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
            'user_id' => new UserSelectInfiniteResource($this->user),
            'third_id' => new ThirdSelectInfiniteResource($this->third),
            'title' => $this->title,
            'emails' => $this->emails_formatted,
            'start_date' => $this->start_date,
            'start_hour' => $this->start_hour,
            'end_date' => $this->end_date,
            'end_hour' => $this->end_hour,
            'all_day' => $this->all_day,
            'description' => $this->description,
            'link' => $this->link,
        ];
    }
}
