<?php

namespace App\Http\Resources\File;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class FileListTableV2Resource extends JsonResource
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
            'support_type_name' => $this->supportType?->name,
            'pathname' => $this->pathname,
            'filename' => $this->filename,
            'created_at' => Carbon::parse($this->created_at)->format('d-m-Y'),
        ];
    }
}
