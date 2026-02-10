<?php

namespace App\Models;

use App\Enums\ConciliationInvoice\StatusConciliationInvoiceEnum;
use App\Traits\Cacheable;
use Illuminate\Database\Eloquent\Model;

class ConciliationInvoice extends Model
{
    use Cacheable;

    protected $guarded = [];

     protected $casts = [
        'status' => StatusConciliationInvoiceEnum::class,
    ];
}
