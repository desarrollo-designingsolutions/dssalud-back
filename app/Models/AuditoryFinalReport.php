<?php

namespace App\Models;

use App\Traits\Cacheable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AuditoryFinalReport extends Model
{
    use Cacheable, HasUuids, SoftDeletes;

    public function invoiceAudit()
    {
        return $this->belongsTo(InvoiceAudit::class, 'factura_id', 'id');
    }

    public function conciliationResult()
    {
        return $this->hasOne(ConciliationResult::class, 'auditory_final_report_id', 'id')
            ->where('invoice_audit_id', $this->factura_id);
    }
}
