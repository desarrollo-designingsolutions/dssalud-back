<?php

namespace App\Models;

use App\Traits\Cacheable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CodeGlosa extends Model
{
    use HasUuids, HasFactory, Cacheable;

    protected $customCachePrefixes = [
        'string:{table}_list*',
    ];
}
