<?php

namespace App\Http\Resources\Schedule;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SchedulePaginateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $response_date = $this->scheduleable?->response_date ? Carbon::parse($this->scheduleable?->response_date)->format('d-m-Y H:i') : 'Sin respuesta';

        return [
            'id' => $this->id,
            'title' => $this->title,
            'start_date' => $this->start_date.' '.$this->start_hour,
            'response_status_backgroundColor' => $this->scheduleable?->response_status?->backgroundColor(),
            'response_status_description' => $this->scheduleable?->response_status?->description(),
            'response_date' => $response_date,
            'user_name' => $this->scheduleable?->user?->full_name,
            'third_name' => $this->scheduleable?->third?->nit.' - '.$this->scheduleable?->third?->name,
            'reconciliation_group_name' => $this->scheduleable?->reconciliation_group?->name,
        ];
    }
}
