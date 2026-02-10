<?php

namespace App\Models;

use App\Traits\Cacheable;
use Illuminate\Database\Eloquent\Model;

class UserThird extends Model
{
    use Cacheable;

    protected $fillable = [
        'user_id',
        'third_id',
    ];

    public function third()
    {
        return $this->belongsTo(Third::class);
    }
}
