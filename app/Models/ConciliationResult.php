<?php

namespace App\Models;

use App\Traits\Cacheable;
use Illuminate\Database\Eloquent\Model;

class ConciliationResult extends Model
{
    use Cacheable;

    protected $guarded = [];
}
