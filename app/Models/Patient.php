<?php

namespace App\Models;

use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use HasFactory, HasUuids, Searchable, SoftDeletes;

    public function getFullNameAttribute()
    {
        return $this->first_name.' '.$this->second_name.' '.$this->first_surname.' '.$this->second_surname;
    }
}
