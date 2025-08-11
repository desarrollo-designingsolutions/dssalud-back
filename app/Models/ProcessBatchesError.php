<?php

namespace App\Models;

use App\Traits\Cacheable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ProcessBatchesError extends Model
{
    use Cacheable, HasUuids;

    protected $guarded = [];
}
