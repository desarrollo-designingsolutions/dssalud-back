<?php

namespace App\Models;

use App\Traits\Cacheable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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

    public static function scopeUserNamesForThirds($query, string $value, array $request)
    {
        $query->orWhereExists(function ($existsQuery) use ($value, $request) {
            $existsQuery->select(DB::raw(1))
                ->from('assignments')
                ->join('users', 'users.id', '=', 'assignments.user_id')
                ->join('invoice_audits', 'invoice_audits.id', '=', 'assignments.invoice_audit_id')
                ->whereColumn('invoice_audits.third_id', 'thirds.id')
                ->where(function ($nameQuery) use ($value) {
                    $nameQuery->where('users.name', 'like', "%$value%")
                        ->orWhere('users.surname', 'like', "%$value%");
                });

            if (! empty($request['assignment_batch_id'])) {
                $existsQuery->where('assignments.assignment_batch_id', $request['assignment_batch_id']);
            }

            if (! empty($request['company_id'])) {
                $existsQuery->where('assignments.company_id', $request['company_id']);
            }

            if (! empty($request['user_id'])) {
                $existsQuery->where('assignments.user_id', $request['user_id']);
            }
        });
    }
}
