<?php

namespace App\Models;

use App\Traits\Cacheable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Medicine extends Model
{
    use Cacheable, HasFactory, HasUuids, SoftDeletes;

    protected $guarded = [];

    public function service()
    {
        return $this->morphOne(Service::class, 'serviceable');
    }

    public function codDiagnosticoPrincipal()
    {
        return $this->belongsTo(Cie10::class, 'codDiagnosticoPrincipal_id');
    }

    public function codDiagnosticoRelacionado()
    {
        return $this->belongsTo(Cie10::class, 'codDiagnosticoRelacionado_id');
    }

    public function tipoMedicamento()
    {
        return $this->belongsTo(TipoMedicamentoPosVersion2::class, 'tipoMedicamento_id');
    }

    public function unidadMedida()
    {
        return $this->belongsTo(Umm::class, 'unidadMedida_id');
    }

    public function conceptoRecaudo()
    {
        return $this->belongsTo(ConceptoRecaudo::class, 'conceptoRecaudo_id');
    }

    public function tipoDocumentoIdentificacion()
    {
        return $this->belongsTo(TipoIdPisis::class, 'tipoDocumentoIdentificacion_id');
    }

    public function unidadMinDispensa()
    {
        return $this->belongsTo(Upr::class, 'unidadMinDispensa_id');
    }

    public function formaFarmaceutica()
    {
        return $this->belongsTo(Ffm::class, 'formaFarmaceutica_id');
    }

    public function nomTecnologiaSalud()
    {
        return $this->belongsTo(Dci::class, 'nomTecnologiaSalud_id');
    }

    public function codTecnologiaSaludable()
    {
        return $this->morphTo(__FUNCTION__, 'codTecnologiaSaludable_type', 'codTecnologiaSaludable_id');
    }
}
