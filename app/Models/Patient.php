<?php

namespace App\Models;

use App\Enums\Assignment\StatusAssignmentEnum;
use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use HasFactory, HasUuids, Searchable, SoftDeletes;

    public function getFullNameAttribute()
    {
        return $this->first_name.' '.$this->second_name.' '.$this->first_surname.' '.$this->second_surname;
    }

    public function invoice_audit(): BelongsTo
    {
        return $this->belongsTo(InvoiceAudit::class);
    }

    public function glosas()
    {
        return $this->hasManyThrough(
            Glosa::class,
            Service::class
        );
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    public function sumGlosasTotalValue()
    {
        return $this->glosas()->sum('glosa_value');
    }

    public function assignmentStatusFor($request): string
    {
        $hasPending = $this->invoice_audit
            ->assignment()
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
}
