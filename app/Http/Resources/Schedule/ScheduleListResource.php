<?php

namespace App\Http\Resources\Schedule;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class ScheduleListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $start = null;
        $end = null;

        // Validar que ambas partes de la fecha y hora existan antes de combinar
        if ($this->start_date && $this->start_hour) {
            $start = Carbon::parse($this->start_date . ' ' . $this->start_hour)->toIso8601String();
        }

        if ($this->end_date && $this->end_hour) {
            $end = Carbon::parse($this->end_date . ' ' . $this->end_hour)->toIso8601String();
        }

        return [
            'id' => $this->id,
            'title' => $this->title,

            'start' => $start,
            'end' => $end,
            'backgroundColor' => $this->type_event?->backgroundColor(),
            'abc' => $this->type_event?->backgroundColor(),
            'type' => 'event', // Agregado para indicar que es un evento
        ];
    }
}
