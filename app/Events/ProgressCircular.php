<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProgressCircular implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $channel;
    public $progress;

    public function __construct($channel, $progress)
    {
        $this->channel = $channel;
        $this->progress = round($progress);
    }

    public function broadcastOn()
    {
        return new Channel($this->channel);
    }

}
