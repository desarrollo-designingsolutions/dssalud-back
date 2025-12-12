<?php

namespace App\Http\Resources\Role;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $quantity_users = 3;

        $allUsers = $this->allUsers;

        $count_users = $allUsers->count();

        if ($count_users > 0) {
            $count_users = $allUsers->count() - $quantity_users;
            $count_users = $count_users < 0 ? 0 : $count_users;
        } else {
            $count_users = 0;
        }

        return [
            'id' => $this->id,
            'description' => $this->description,
            'users' => $allUsers->select(['name', 'surname'])->take($quantity_users),
            'count_users_extras' => $count_users,
            'count_users' => $allUsers->count(),
        ];
    }
}
