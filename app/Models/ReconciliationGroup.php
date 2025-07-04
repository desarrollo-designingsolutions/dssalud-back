<?php

namespace App\Models;

use App\Traits\Cacheable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReconciliationGroup extends Model
{
    use HasUuids, SoftDeletes, Cacheable;

    protected $customCachePrefixes = [
        'string:{table}_list*',
    ];

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
}
