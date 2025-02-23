<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class FilingProgressEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $filing_id;
    public $progress;


    /**
     * Create a new event instance.
     */
    public function __construct($filing_id, $progress)
    {
        $this->filing_id = $filing_id;
        $this->progress = $progress;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn()
    {
        // Define el canal que usará el evento para emitir
        return new Channel("filing.{$this->filing_id}");
    }

    public function broadcastAs()
    {
        return 'FilingProgressEvent'; // Nombre del evento que escuchará el frontend
    }
}
