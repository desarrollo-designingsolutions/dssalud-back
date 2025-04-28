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

    /**
     * Scope para filtrar por nombres o apellidos de usuarios en asignaciones.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $value
     * @param array $request
     * @return void
     */
    public static function scopeUserNames($query, string $value, array $request)
    {
        // Búsqueda en users.name o users.surname
        $conditions = [];
        $bindings = [];

        // Condición para assignment_batch_id
        if (!empty($request['assignment_batch_id'])) {
            $conditions[] = 'assignments.assignment_batch_id = ?';
            $bindings[] = $request['assignment_batch_id'];
        }

        // Condición para company_id
        if (!empty($request['company_id'])) {
            $conditions[] = 'assignments.company_id = ?';
            $bindings[] = $request['company_id'];
        }

        // Condición para third_id
        if (!empty($request['third_id'])) {
            $conditions[] = 'invoice_audits.third_id = ?';
            $bindings[] = $request['third_id'];
        }

        if (!empty($request['user_id'])) {
            $conditions[] = 'assignments.user_id = ?';
            $bindings[] = $request['user_id'];
        }

        // Construir la consulta con todas las condiciones
        $conditionString = $conditions ? ' AND ' . implode(' AND ', $conditions) : '';
        $bindings = array_merge($bindings, ["%$value%", "%$value%"]);

        $query->orWhereRaw('EXISTS (
            SELECT 1
            FROM assignments
            INNER JOIN users ON users.id = assignments.user_id
            INNER JOIN invoice_audits ON invoice_audits.id = assignments.invoice_audit_id
            WHERE assignments.invoice_audit_id = invoice_audits.id
            ' . $conditionString . '
            AND (
                users.name LIKE ?
                OR COALESCE(users.surname, \'\') LIKE ?
            )
        )', $bindings);
    }
}
