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

    public function countInvoiceByFilter(Array $filter = [])
    {
        return $this->invoiceAudits()->whereHas('assignment', function($query) use($filter) {

            if (!empty($filter['status'])) {
                $query->where('status', $filter['status']);
            }
            
        })->count(); // Filtramos por el campo status en Assignment
    }
    
}
