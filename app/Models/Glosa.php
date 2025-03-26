<?php

namespace App\Models;

use App\Traits\Cacheable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Glosa extends Model
{
    use HasFactory,  HasUuids, Cacheable;

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
