<?php

namespace App\Models;

use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceAudit extends Model
{
    use HasFactory, HasUuids, Searchable, SoftDeletes;

    public function files()
    {
        return $this->morphMany(File::class, 'fileable');
    }

    public function third()
    {
        return $this->belongsTo(Third::class, 'third_id');
    }

    public function assignment()
    {
        return $this->hasMany(Assignment::class);
    }

    public function patients()
    {
        return $this->hasMany(Patient::class);
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    public function sumServicesTotalValue()
    {
        return $this->services()->sum('total_value');
    }
}
