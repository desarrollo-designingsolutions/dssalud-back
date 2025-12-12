<?php

namespace App\Models;

use App\Enums\Assignment\StatusAssignmentEnum;
use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceAudit extends Model
{
    protected $guarded = [];

    use HasFactory, HasUuids, Searchable, SoftDeletes;

    public function files()
    {
        return $this->morphMany(File::class, 'fileable');
    }

    public function third()
    {
        return $this->belongsTo(Third::class, 'third_id');
    }

    public function filingInvoice()
    {
        return $this->belongsTo(FilingInvoice::class, 'filing_invoice_id');
    }

    public function assignment()
    {
        return $this->hasMany(Assignment::class);
    }

    public function patients()
    {
        return $this->hasMany(Patient::class);
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    public function sumServicesTotalValue()
    {
        return $this->services()->sum('total_value');
    }

    public function glosas()
    {
        return $this->hasManyThrough(
            Glosa::class,
            Service::class,
            'invoice_audit_id',
            'service_id',
            'id',
            'id'
        );
    }

    public function sumGlosasTotalValue()
    {
        return $this->glosas()->sum('glosa_value');
    }

    public function assignmentStatusFor($request): string
    {
        $hasPending = $this->assignment()
            ->whereNot('status', StatusAssignmentEnum::ASSIGNMENT_EST_003->value)
            ->where(function ($query) use ($request) {
                if (! empty($request['user_id'])) {
                    $query->where('user_id', $request['user_id']);
                }
            })
            ->exists(); // consulta eficiente, no carga todos los registros :contentReference[oaicite:1]{index=1}

        return $hasPending
            ? 'pending' // 'Pendiente'
            : 'finished'; // 'Finalizado'
    }

    public function auditoryFinalReport()
    {
        return $this->belongsTo(AuditoryFinalReport::class, 'id', 'factura_id');
    }

    public function sumValorGlosa()
    {
        return $this->auditoryFinalReport()->sum('valor_glosa');
    }
}
