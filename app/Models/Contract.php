<?php

namespace App\Models;

use App\Traits\Cacheable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use Cacheable, HasUuids, SoftDeletes;

    public function third()
    {
        return $this->belongsTo(Third::class);
    }
}
