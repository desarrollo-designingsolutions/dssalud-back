<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Third extends Model
{
    use HasUuids, SoftDeletes;

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function invoiceAudits()
    {
        return $this->hasMany(InvoiceAudit::class);
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

    public function countInvoiceByStatus(string $status)
    {
        return $this->invoiceAudits()->whereHas('assignment', function($query) use($status) {
            $query->where('status', $status);
        })->count(); // Filtramos por el campo status en Assignment
    }
    
}
