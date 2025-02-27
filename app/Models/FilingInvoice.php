<?php

namespace App\Models;

use App\Enums\Filing\StatusFillingInvoiceEnum;
use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class FilingInvoice extends Model
{
    use HasUuids, SoftDeletes, Searchable;

    protected $casts = [
        'status' => StatusFillingInvoiceEnum::class,
        'status_xml' => StatusFillingInvoiceEnum::class,
    ];

    public static function boot()
    {
        parent::boot();

        // Asigna un número de caso automáticamente antes de crear un nuevo registro
        static::creating(function ($model) {
            DB::transaction(function () use ($model) {
                $numberCaseInitial = env("NUMBER_CASE_INITIAL", 0); // Número inicial de caso si no hay registros previos

                // Obtener el último registro ordenado por el número de caso de manera descendente
                $lastFiling = static::orderBy('case_number', 'desc')->lockForUpdate()->first();

                // Generar el siguiente número de caso al nuevo registro
                $case_number = $lastFiling ? (int)$lastFiling->case_number + 1 : $numberCaseInitial;

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
}
