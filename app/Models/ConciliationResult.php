<?php

namespace App\Models;

use App\Traits\Cacheable;
use Illuminate\Database\Eloquent\Model;

class ConciliationResult extends Model
{
    use Cacheable;

    protected $guarded = [];


    public function invoiceAudit()
    {
        return $this->belongsTo(InvoiceAudit::class);
    }

    public function auditoryFinalReport()
    {
        return $this->belongsTo(AuditoryFinalReport::class);
    }

    public function reconciliationGroup()
    {
        return $this->belongsTo(ReconciliationGroup::class);
    }
}
