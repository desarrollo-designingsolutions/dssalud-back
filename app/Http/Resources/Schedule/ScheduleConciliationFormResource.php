<?php

namespace App\Http\Resources\Schedule;

use App\Http\Resources\ReconciliationGroup\ReconciliationGroupSelectInfiniteResource;
use App\Http\Resources\Third\ThirdSelectInfiniteResource;
use App\Http\Resources\User\UserSelectInfiniteResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleConciliationFormResource extends JsonResource
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
            'user_id' => new UserSelectInfiniteResource($this->scheduleable?->user),
            'third_id' => new ThirdSelectInfiniteResource($this->scheduleable?->third),
            'reconciliation_group_id' => new ReconciliationGroupSelectInfiniteResource($this->scheduleable?->reconciliation_group),
            'title' => $this->title,
            'emails' => $this->emails_formatted_string,
            'start_date' => $this->start_date,
            'start_hour' => $this->start_hour,
            'end_date' => $this->end_date,
            'end_hour' => $this->end_hour,
            'all_day' => $this->all_day,
            'description' => $this->description,
            'link' => $this->scheduleable->link,
        ];
    }
}
