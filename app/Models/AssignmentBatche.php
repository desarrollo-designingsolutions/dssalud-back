<?php

namespace App\Models;

use App\Traits\Cacheable;
use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class AssignmentBatche extends Model
{
    use HasUuids, Cacheable;

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class, 'assignment_batch_id');
    }

    // Relación HasManyThrough para obtener las facturas
    public function invoices(): HasManyThrough
    {
        return $this->hasManyThrough(
            InvoiceAudit::class,    // Modelo destino (facturas)
            Assignment::class,      // Modelo intermedio (assignments)
            'assignment_batch_id',  // Clave foránea en Assignment que apunta a AssignmentBatche
            'id',                   // Clave primaria en InvoiceAudit
            'id',                   // Clave primaria en AssignmentBatche
            'invoice_audit_id'      // Clave foránea en Assignment que apunta a InvoiceAudit
        );
    }

    // Método para obtener facturas por estado
    public function countInvoiceByFilter(array $filter = [])
    {
        $query = $this->invoices();

        if (!empty($filter['status'])) {
            $query->where('assignments.status', $filter['status']);
        }

        if (!empty($filter['user_id'])) {
            $query->whereHas('third.user', function ($subQuery) use ($filter) {
                $subQuery->where('id', $filter['user_id']);
            });
        }
        return $query->count();
    }
}
