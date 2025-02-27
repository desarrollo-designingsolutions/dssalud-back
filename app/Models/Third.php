<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Third extends Model
{
    use HasUuids, SoftDeletes;

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
