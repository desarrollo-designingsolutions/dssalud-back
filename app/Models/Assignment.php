<?php

namespace App\Models;

use App\Traits\Cacheable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Assignment extends Model
{
    use Cacheable, HasUuids;

    protected $guarded = [];

    public function invoiceAudit()
    {
        return $this->belongsTo(InvoiceAudit::class, 'invoice_audit_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignmentBatche()
    {
        return $this->belongsTo(AssignmentBatche::class, 'assignment_batch_id');
    }

    public function thrids()
    {
        return $this->hasOneThrough(
            Third::class,           // Modelo destino (Third)
            InvoiceAudit::class,    // Modelo intermedio (InvoiceAudit)
            'id',                   // Clave foránea en InvoiceAudit que apunta a Assignment
            'id',                   // Clave primaria en Third
            'invoice_audit_id',     // Clave foránea en Assignment que apunta a InvoiceAudit
            'third_id'              // Clave foránea en InvoiceAudit que apunta a Third
        );
    }
}
