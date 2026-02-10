<?php

namespace App\Events;

use App\Models\Filing;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FilingFinishProcessJob implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $filing;

    /**
     * Create a new event instance.
     */
    public function __construct($filingId)
    {
        $this->filing = Filing::find($filingId);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn()
    {
        // Define el canal que usará el evento para emitir
        return new Channel("filing.{$this->filing->id}");
    }

    public function broadcastAs()
    {
        return 'FilingFinishProcessJob'; // Nombre del evento que escuchará el frontend
    }

    public function broadcastWith()
    {
        // Aquí puedes incluir los datos que deseas enviar al frontend

        return [
            'id' => $this->filing->id,
            'has_validation_errors' => $this->filing->has_validation_errors,
            'status' => $this->filing->status,
            'error_status' => $this->filing->error_status,
        ];
    }
}
