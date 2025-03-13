<?php

namespace App\Models;

use App\Traits\Cacheable;
use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use Cacheable, HasFactory, HasUuids,Searchable, SoftDeletes;

    protected $casts = [
        'is_active' => 'boolean',
        'final_date' => 'datetime',
        'country_id' => 'integer',
        'state_id' => 'integer',
        'city_id' => 'integer',
    ];

    public function country()
    {
        return $this->hasOne(Country::class, 'id', 'country_id');
    }

    public function state()
    {
        return $this->hasOne(State::class, 'id', 'state_id');
    }

    public function city()
    {
        return $this->hasOne(City::class, 'id', 'city_id');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'company_id', 'id');
    }

    public function nits()
    {
        return $this->hasMany(CompanyNit::class, 'company_id', 'id');
    }
}
