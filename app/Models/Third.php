<?php

namespace App\Models;

use App\Enums\Assignment\StatusAssignmentEnum;
use App\Traits\Cacheable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Third extends Model
{
    use Cacheable, HasUuids, SoftDeletes;

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function invoiceAudits()
    {
        return $this->hasMany(InvoiceAudit::class);
    }

    public function user()
    {
        return $this->hasMany(User::class);
    }

    public function assignedInvoiceAudits()
    {
        return $this->hasMany(InvoiceAudit::class)
            ->whereHas('assignment');
    }

    public function sumInvoiceAuditsTotalValue()
    {
        return $this->invoiceAudits()->sum('total_value');
    }

    public function countInvoiceByFilter(array $filter = [])
    {
        return $this->invoiceAudits()->whereHas('assignment', function ($query) use ($filter) {

            if (!empty($filter['status'])) {
                $query->where('status', $filter['status']);
            }
        })->count(); // Filtramos por el campo status en Assignment
    }

    // Nueva relación para obtener las asignaciones a través de InvoiceAudit
    public function assignments()
    {
        return $this->hasManyThrough(
            Assignment::class,      // Modelo destino (Assignment)
            InvoiceAudit::class,    // Modelo intermedio (InvoiceAudit)
            'third_id',             // Clave foránea en InvoiceAudit que apunta a Third
            'invoice_audit_id',     // Clave foránea en Assignment que apunta a InvoiceAudit
            'id',                   // Clave primaria en Third
            'id'                    // Clave primaria en InvoiceAudit
        );
    }

    public function assignmentStatusFor($request): string
    {
        $hasPending = $this->assignments()
            ->whereNot('status', StatusAssignmentEnum::ASSIGNMENT_EST_003->value)
            ->where(function ($query) use ($request) {
                if (!empty($request['user_id'])) {
                    $query->where('user_id', $request['user_id']);
                }
            })
            ->exists(); // consulta eficiente, no carga todos los registros :contentReference[oaicite:1]{index=1}

        return $hasPending
            ? 'pending' // 'Pendiente'
            : 'finished'; // 'Finalizado'
    }


    public function departmentAndCity()
    {
        return $this->hasOne(ThirdDepartment::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_thirds', 'third_id', 'user_id');
    }

    public function userThirds()
    {
        return $this->hasMany(UserThird::class);
    }
}
