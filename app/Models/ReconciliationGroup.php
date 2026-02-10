<?php

namespace App\Models;

use App\Enums\ReconciliationGroup\StatusReconciliationGroupEnum;
use App\Traits\Cacheable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReconciliationGroup extends Model
{
    use Cacheable, HasUuids, SoftDeletes;


    protected function casts(): array
    {
        return [
            'status' => StatusReconciliationGroupEnum::class,
        ];
    }


    public function third()
    {
        return $this->belongsTo(Third::class);
    }

    public function invoices()
    {
        return $this->belongsToMany(InvoiceAudit::class, 'reconciliation_group_invoices', 'reconciliation_group_id', 'invoice_audit_id');
    }

    public function reconciliationNotification()
    {
        return $this->belongsTo(ReconciliationNotification::class, 'id', 'reconciliation_group_id');
    }

    public function thirdsFromAuditoryFinalReport()
    {
        return $this->hasMany(AuditoryFinalReport::class,"nit","third_id");
    }

    public function conciliationResult()
    {
        return $this->hasMany(ConciliationResult::class,"reconciliation_group_id","id");
    }

}
