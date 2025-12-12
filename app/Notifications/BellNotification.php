<?php

namespace App\Notifications;

use Illuminate\Broadcasting\Channel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

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
            'notifiable_id' => $notifiable['id'],
            'notifiable_type' => $this->getNotifiableType(),
            'title' => $this->data['title'],
            'subtitle' => $this->data['subtitle'],
            'action_url' => $this->getActionUrl(),
            'openInNewTab' => $this->getOpenInNewTab(),
            'img' => $this->getImg($notifiable),
            'text' => $this->getText($notifiable),
        ];
    }

    public function toBroadcast($notifiable)
    {
        $this->noti = $notifiable;
        $activeNotificationsCount = $notifiable->notificaciones->whereNull('read_at')->where('is_removed', 0)->count();

        return new BroadcastMessage([
            'activeNotificationsCount' => $activeNotificationsCount,
            'notifiable_id' => $notifiable['id'],
            'notifiable_type' => $this->getNotifiableType(),
            'title' => $this->data['title'],
            'subtitle' => $this->data['subtitle'],
            'action_url' => $this->getActionUrl(),
            'openInNewTab' => $this->getOpenInNewTab(),
            'img' => $this->getImg($notifiable),
            'text' => $this->getText($notifiable),
        ]);
    }

    public function broadcastAs()
    {
        return 'bell-notification';
    }

    public function broadcastOn()
    {
        return new Channel('user.'.$this->noti['id']);
    }

    protected function getNotifiableType()
    {
        return 'App\\Models\\User';
    }

    protected function getActionUrl()
    {
        return $this->data['action_url'] ?? null;
    }
    protected function getOpenInNewTab()
    {
        return $this->data['openInNewTab'] ?? false;
    }

    protected function getImg($notifiable)
    {

        // Si img está presente y no está vacío, retornar img
        if (isset($this->data['img']) && ! empty($this->data['img'])) {
            return $this->data['img'];
        }

        // Si no hay img, intentar retornar la photo del usuario
        if (isset($notifiable['photo']) && ! empty($notifiable['photo'])) {
            return $notifiable['photo'];
        }

        // Si no hay photo del usuario, intentar retornar la foto de la empresa
        if (isset($notifiable->company) && isset($notifiable->company['logo']) && ! empty($notifiable->company['logo'])) {
            return $notifiable->company['logo'];
        }

        // Si no hay photo, retornar null
        return null;
    }

    protected function getText($notifiable)
    {
        // Si existe img en data, retornar null (la imagen prevalece)
        if (isset($this->data['img']) && ! empty($this->data['img'])) {
            return null;
        }

        // Si existe la photo del usuario, retornar null
        if (isset($notifiable['photo']) && ! empty($notifiable['photo'])) {
            return null;
        }

        // Si text está definido y no está vacío, retornar text
        if (isset($this->data['text']) && $this->data['text'] !== '') {
            return $this->data['text'];
        }

        // Si no hay text, retornar el nombre completo del usuario (name + surname)
        $name = isset($notifiable['name']) && ! empty($notifiable['name']) ? $notifiable['name'] : '';
        $surname = isset($notifiable['surname']) && ! empty($notifiable['surname']) ? $notifiable['surname'] : '';
        $fullName = trim($name.' '.$surname);

        return ! empty($fullName) ? $fullName : null;
    }
}
