<?php

namespace App\Http\Resources\Audit;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditResource extends JsonResource
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
            'event' => $this->event,
            'action' => $this->action,
            'created_at' => $this->created_at->toIso8601String(), // Debería enviar la fecha en formato ISO 8601
            'date' => $this->created_at->format('d-m-Y g:i A'),
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'user_full_name' => $this->user?->full_name,
            'user_email' => $this->user?->email,
            'user_role' => $this->user?->role?->description,
            'photo' => $this->user?->photo,
            'dot' => $this->getIcon($this->event),
        ];
    }

    protected function getIcon($event)
    {
        return match ($event) {
            'created' => [
                'icon' => 'tabler-device-floppy',
                'color' => 'success',
            ],
            'updated' => [
                'icon' => 'tabler-pencil',
                'color' => 'info',
            ],
            'deleted' => [
                'icon' => 'tabler-trash',
                'color' => 'error',
            ],
            default => [
                'icon' => 'tabler-question',
                'color' => 'secondary',
            ], // Añade un valor por defecto en caso de que el evento no coincida
        };
    }
}
