<?php

namespace App\Models;

use App\Enums\SupportType\SupportTypeModuleEnum;
use App\Traits\Cacheable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupportType extends Model
{
    use Cacheable, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'module' => SupportTypeModuleEnum::class,
        ];
    }
}
