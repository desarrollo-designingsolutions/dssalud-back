<?php

namespace App\Http\Resources\File;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FileListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fileable_type' => $this->fileable_type,
            'fileable_id' => $this->fileable_id,
            'pathname' => $this->pathname,
            'filename' => $this->filename,
            'created_at' => $this->created_at->format('d/m/Y'),
        ];
    }
}
