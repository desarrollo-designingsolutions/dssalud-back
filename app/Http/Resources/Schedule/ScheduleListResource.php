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
        // Formatear el día para que sea compatible con FullCalendar
        $formattedDay = Carbon::createFromFormat('Y-m-d', $this->start_date)->toDateString();
        $formattedEndDay = Carbon::createFromFormat('Y-m-d', $this->end_date)->toDateString();

        return [
            'id' => $this->id,
            'title' => $this->title,

            // para que se ordene y arregle en el calendario
            'start' => $formattedDay.'T'.$this->start_hour,
            'end' => $formattedEndDay.'T'.$this->end_hour,

            'backgroundColor' => $this->type_event?->backgroundColor(),
            'type' => 'event', // Agregado para indicar que es un evento
        ];
    }
}
