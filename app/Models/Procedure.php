<?php

namespace App\Models;

use App\Traits\Cacheable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Procedure extends Model
{
    use Cacheable, HasFactory, HasUuids, SoftDeletes;

    protected $guarded = [];

    public function service()
    {
        return $this->morphOne(Service::class, 'serviceable');
    }

    public function codProcedimiento()
    {
        return $this->belongsTo(CupsRips::class, 'codProcedimiento_id');
    }

    public function viaIngresoServicioSalud()
    {
        return $this->belongsTo(ViaIngresoUsuario::class, 'viaIngresoServicioSalud_id');
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

    public function codDiagnosticoPrincipal()
    {
        return $this->belongsTo(Cie10::class, 'codDiagnosticoPrincipal_id');
    }

    public function codDiagnosticoRelacionado()
    {
        return $this->belongsTo(Cie10::class, 'codDiagnosticoRelacionado_id');
    }

    public function codComplicacion()
    {
        return $this->belongsTo(Cie10::class, 'codComplicacion_id');
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
