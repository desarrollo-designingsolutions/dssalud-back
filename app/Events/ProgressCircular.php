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

    public $success;

    public function __construct($channel, $progress, $success = true)
    {
        $this->channel = $channel;
        $this->progress = $progress;
        $this->success = $success;
    }

    public function broadcastOn()
    {
        return new Channel($this->channel);
    }

    public function broadcastWith()
    {
        return [
            'channel' => $this->channel,
            'progress' => sprintf('%.2f', floor($this->progress * 100) / 100),
            'progress_2' => $this->progress,
            'success' => $this->success,
        ];
    }
}
