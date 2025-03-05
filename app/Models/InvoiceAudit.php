<?php

namespace App\Models;

use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceAudit extends Model
{
    use HasUuids, SoftDeletes, Searchable;

    public function files()
    {
        return $this->morphMany(File::class, 'fileable');
    }
}
