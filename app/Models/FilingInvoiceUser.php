<?php

namespace App\Models;

use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FilingInvoiceUser extends Model
{
    use HasUuids, Searchable;
}
