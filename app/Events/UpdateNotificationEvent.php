<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UpdateNotificationEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public $user;

    public $notification_ids;

    public function __construct($user, $notification_ids)
    {
        $this->user = $user;
        $this->notification_ids = $notification_ids;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): Channel
    {
        return new Channel("user.{$this->user->id}");
    }

    public function broadcastWith()
    {
        $user = User::find($this->user->id);
        $activeNotificationsCount = $user->notificaciones->whereNull('read_at')->where('is_removed', 0)->count();
        // $notifications = $user->notificaciones;

        $notifications = $user->notifications->whereIn('id', $this->notification_ids);

        $notifications = $notifications->map(function ($item) {
            return [
                'id' => $item->id,
                'isSeen' => $item->read_at ? true : false,
                'time' => $item->created_at->diffForHumans(),
                'is_removed' => $item->is_removed,

            ];
        });

        return [
            'activeNotificationsCount' => $activeNotificationsCount,
            'notifications' => $notifications->values(),
        ];
    }

    public function broadcastAs()
    {
        return 'update-notification'; // Nombre del evento que será emitido en el canal
    }
}
