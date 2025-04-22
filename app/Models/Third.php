<?php

namespace App\Models;

use App\Traits\Cacheable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Third extends Model
{
    use HasUuids,SoftDeletes, Cacheable;

    protected $customCachePrefixes = [
        'string:{table}_list*',
    ];

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

            if (! empty($filter['status'])) {
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
}
