<?php

namespace App\Models;

use App\Traits\Cacheable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TipoIdPisis extends Model
{
    use Cacheable, HasFactory, HasUuids, SoftDeletes;

    protected $table = 'tipo_id_pisis';
}
