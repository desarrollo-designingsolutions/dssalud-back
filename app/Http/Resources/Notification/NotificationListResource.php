<?php

namespace App\Http\Resources\Notification;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $info = json_decode($this->data, 1);

        return [
            'created_at' => $this->created_at,
            'id' => $this->id,
            'title' => $info['title'],
            'subtitle' => truncate_text($info['subtitle'], 65),
            'action_url' => $info['action_url'],
            'time' => $this->created_at->diffForHumans(),
            'isSeen' => $this->read_at ? true : false,
            'img' => isset($info['img']) ? $info['img'] : null,
            'text' => isset($info['text']) ? $info['text'] : null,
        ];
    }
}
