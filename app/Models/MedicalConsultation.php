<?php

namespace App\Models;

use App\Traits\Cacheable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedicalConsultation extends Model
{
    use Cacheable, HasFactory, HasUuids, SoftDeletes;

    protected $guarded = [];

    public function service()
    {
        return $this->morphOne(Service::class, 'serviceable');
    }

    public function codConsulta()
    {
        return $this->belongsTo(CupsRips::class, 'codConsulta_id');
    }

    public function modalidadGrupoServicioTecSal()
    {
        return $this->belongsTo(ModalidadAtencion::class, 'modalidadGrupoServicioTecSal_id');
    }

    public function grupoServicios()
    {
        return $this->belongsTo(GrupoServicio::class, 'grupoServicios_id');
    }

    public function codServicio()
    {
        return $this->belongsTo(Servicio::class, 'codServicio_id');
    }

    public function finalidadTecnologiaSalud()
    {
        return $this->belongsTo(RipsFinalidadConsultaVersion2::class, 'finalidadTecnologiaSalud_id');
    }

    public function causaMotivoAtencion()
    {
        return $this->belongsTo(RipsCausaExternaVersion2::class, 'causaMotivoAtencion_id');
    }

    public function codDiagnosticoPrincipal()
    {
        return $this->belongsTo(Cie10::class, 'codDiagnosticoPrincipal_id');
    }

    public function codDiagnosticoRelacionado1()
    {
        return $this->belongsTo(Cie10::class, 'codDiagnosticoRelacionado1_id');
    }

    public function codDiagnosticoRelacionado2()
    {
        return $this->belongsTo(Cie10::class, 'codDiagnosticoRelacionado2_id');
    }

    public function codDiagnosticoRelacionado3()
    {
        return $this->belongsTo(Cie10::class, 'codDiagnosticoRelacionado3_id');
    }

    public function tipoDiagnosticoPrincipal()
    {
        return $this->belongsTo(RipsTipoDiagnosticoPrincipalVersion2::class, 'tipoDiagnosticoPrincipal_id');
    }

    public function conceptoRecaudo()
    {
        return $this->belongsTo(ConceptoRecaudo::class, 'conceptoRecaudo_id');
    }

    public function tipoDocumentoIdentificacion()
    {
        return $this->belongsTo(TipoIdPisis::class, 'tipoDocumentoIdentificacion_id');
    }
}
