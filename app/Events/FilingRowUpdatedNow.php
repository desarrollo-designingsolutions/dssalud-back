<?php

namespace App\Events;

use App\Models\Filing;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FilingRowUpdatedNow implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $filing;

    /**
     * Create a new event instance.
     */
    public function __construct($filing_id)
    {
        $this->filing = Filing::find($filing_id);
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

    public function broadcastWith()
    {
        // Aquí puedes incluir los datos que deseas enviar al frontend

        return [
            'id' => $this->filing->id,
            'status' => $this->filing->status,
        ];
    }
}
