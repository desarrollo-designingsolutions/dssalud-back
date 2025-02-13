<?php

namespace App\Http\Resources\Role;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuCheckBoxResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->recursiveToArray($this);
    }

    /**
     * Recursively transform the resource and its children into an array.
     *
     * @param  mixed  $resource
     * @return array<string, mixed>
     */
    protected function recursiveToArray($resource): array
    {
        $result = [
            'id' => $resource->id,
            'title' => $resource->title,
            'check_state' => false,
            'permissions' => $resource->permissions->map(function ($value) {
                return [
                    'id' => $value->id,
                    'description' => $value->description,
                    'check_state' => false,
                ];
            }),
            'children' => $resource->children->map(function ($child) {
                return $this->recursiveToArray($child);
            })->all(),
        ];

        return $result;
    }
}
