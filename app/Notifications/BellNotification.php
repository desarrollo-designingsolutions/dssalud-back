<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Notifications\Messages\BroadcastMessage;

class BellNotification extends Notification
{
    use Queueable;

    public $data;
    public $noti;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'notifiable_id' => $notifiable["id"],
            'notifiable_type' => $this->getNotifiableType(),
            'title' => $this->data['title'],
            'subtitle' => $this->data['subtitle'],
            'action_url' => $this->getActionUrl(),
            'img' => $this->getText($this->data),
            'text' => $this->getText($this->data),
        ];
    }

    public function toBroadcast($notifiable)
    {
        $this->noti = $notifiable;
        $activeNotificationsCount = $notifiable->notificaciones->whereNull("read_at")->where("is_removed", 0)->count();

        return new BroadcastMessage([  // Usa BroadcastMessage para enviar el mensaje
            'activeNotificationsCount' => $activeNotificationsCount,
            'notifiable_id' => $notifiable["id"],
            'notifiable_type' => $this->getNotifiableType(),
            'title' => $this->data['title'],
            'subtitle' => $this->data['subtitle'],
            'action_url' => $this->getActionUrl(),
            'img' => $this->getText($this->data),
            'text' => $this->getText($this->data),
        ]);
    }


    public function broadcastAs()
    {
        return 'bell-notification'; // Nombre del evento que será emitido en el canal
    }


    public function broadcastOn()
    {
        return new Channel('user.' . $this->noti["id"]);
    }

    protected function getNotifiableType()
    {

        return "App\\Models\\User";
    }

    protected function getImg()
    {
        return empty($this->data['img']) ? null : $this->data['img'];
    }
    protected function getActionUrl()
    {
        return $this->data['action_url'] ?? null;
    }

    protected function getText()
    {
        // Verifica si 'text' está definido y no está vacío
        if (isset($this->data['text']) && $this->data['text'] !== '') {
            return $this->data['text'];
        }

        // Opcional: lógica si img está presente
        if (isset($this->data['img']) && !empty($this->data['img'])) {
            return null; // O algún valor por defecto
        }

        return null;
    }
}
