<?php

namespace App\Models;

use App\Traits\Cacheable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReconciliationGroupInvoice extends Model
{
    use Cacheable, HasUuids, SoftDeletes;

    public function reconciliationGroup()
    {
        return $this->belongsTo(ReconciliationGroup::class, 'reconciliation_group_id');
    }

    public function invoiceAudit()
    {
        return $this->belongsTo(InvoiceAudit::class, 'invoice_audit_id');
    }

    public function conciliation_invoice()
    {
        return $this->hasOne(ConciliationInvoice::class, 'invoice_audit_id',"invoice_audit_id");
    }

    public function conciliation_result()
    {
        return $this->hasMany(ConciliationResult::class, 'invoice_audit_id',"invoice_audit_id");
    }
}
