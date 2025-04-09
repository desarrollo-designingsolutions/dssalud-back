<?php

namespace App\Models;

use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory, HasUuids, Searchable, SoftDeletes;

    protected $guarded = [];

    public function glosas(): HasMany
    {
        return $this->hasMany(Glosa::class);
    }

    public function invoice_audit(): BelongsTo
    {
        return $this->belongsTo(InvoiceAudit::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
