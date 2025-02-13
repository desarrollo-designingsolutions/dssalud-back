<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class File extends Model
{
    use HasFactory, HasUuids, SoftDeletes;


    public function fileable()
    {
        return $this->morphTo(__FUNCTION__, 'fileable_type', 'fileable_id');
    }

    public function user()
    {
        return $this->hasOne(User::class,"id","user_id");
    }
}
