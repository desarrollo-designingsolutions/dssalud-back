<?php

namespace App\Models;

use App\Enums\Filing\StatusFillingInvoiceEnum;
use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FilingInvoice extends Model
{
    use HasUuids, SoftDeletes, Searchable;

    protected $casts = [
        'status' => StatusFillingInvoiceEnum::class,
        'status_xml' => StatusFillingInvoiceEnum::class,
    ];

    public function filing(): BelongsTo
    {
        return $this->belongsTo(Filing::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function filingInvoiceUsers(): HasMany
    {
        return $this->hasMany(FilingInvoiceUser::class, 'filing_invoice_id');
    }

    public function company()
    {
        return $this->filing->company();
    }


    public function files()
    {
        return $this->morphMany(File::class, 'fileable');
    }

    public function getFilesCountAttribute(): int
    {
        return $this->files()->count();
    }
}
