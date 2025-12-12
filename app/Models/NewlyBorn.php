<?php

namespace App\Models;

use App\Traits\Cacheable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NewlyBorn extends Model
{
    use Cacheable, HasFactory, HasUuids, SoftDeletes;

    protected $guarded = [];

    public function service()
    {
        return $this->morphOne(Service::class, 'serviceable');
    }

    public function codSexoBiologico()
    {
        return $this->belongsTo(Sexo::class, 'codSexoBiologico_id');
    }

    public function codDiagnosticoPrincipal()
    {
        return $this->belongsTo(Cie10::class, 'codDiagnosticoPrincipal_id');
    }

    public function codDiagnosticoCausaMuerte()
    {
        return $this->belongsTo(Cie10::class, 'codDiagnosticoCausaMuerte_id');
    }

    public function condicionDestinoUsuarioEgreso()
    {
        return $this->belongsTo(CondicionyDestinoUsuarioEgreso::class, 'condicionDestinoUsuarioEgreso_id');
    }

    public function tipoDocumentoIdentificacion()
    {
        return $this->belongsTo(TipoIdPisis::class, 'tipoDocumentoIdentificacion_id');
    }
}
