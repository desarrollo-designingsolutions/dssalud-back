<?php

namespace App\Models;

use App\Enums\Filing\StatusFilingInvoiceEnum;
use App\Helpers\Constants;
use App\Traits\Cacheable;
use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FilingInvoice extends Model
{
    use Cacheable, HasUuids, Searchable,SoftDeletes;

    protected $casts = [
        'status' => StatusFilingInvoiceEnum::class,
        'status_xml' => StatusFilingInvoiceEnum::class,
    ];

    public static function boot()
    {
        parent::boot();

        // Asigna un número de caso automáticamente antes de crear un nuevo registro
        static::creating(function ($model) {
            DB::transaction(function () use ($model) {
                $numberCaseInitial = Constants::NUMBER_CASE_INITIAL; // Número inicial de caso si no hay registros previos

                // Obtener el último registro ordenado por el número de caso de manera descendente
                // $lastFiling = static::orderBy('case_number', 'desc')->lockForUpdate()->first();
                $lastFiling = static::orderByRaw('CAST(case_number AS UNSIGNED) DESC')->lockForUpdate()->first();

                // Generar el siguiente número de caso al nuevo registro
                $case_number = $lastFiling ? (int) $lastFiling->case_number + 1 : $numberCaseInitial;

                Log::info('Asignando número de caso: ' . $case_number);
                // Asignar el siguiente número de caso al nuevo registro
                $model->case_number = $case_number;
            });
        });
    }

    public function filing(): BelongsTo
    {
        return $this->belongsTo(Filing::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function filingInvoiceUsers(): HasMany
    {
        return $this->hasMany(FilingInvoiceUser::class, 'filing_invoice_id');
    }

    public function company()
    {
        return $this->filing->company();
    }

    public function files()
    {
        return $this->morphMany(File::class, 'fileable');
    }

    public function getFilesCountAttribute(): int
    {
        return $this->files()->count();
    }
    
    public function invoiceAudit()
    {
        return $this->hasOne(InvoiceAudit::class, 'filing_invoice_id', 'id');
    }
}
