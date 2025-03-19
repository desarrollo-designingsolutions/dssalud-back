<?php

namespace App\Models;

use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceAudit extends Model
{
    use HasUuids, Searchable, SoftDeletes, HasFactory;

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
}
