<?php

namespace App\Models;

use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FilingInvoiceUser extends Model
{
    use HasUuids, Searchable;

    public function filing_invoice(): BelongsTo
    {
        return $this->belongsTo(FilingInvoice::class);
    }
}
