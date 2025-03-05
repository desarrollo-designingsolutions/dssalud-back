<?php

namespace App\Models;

use App\Enums\Filing\StatusFilingEnum;
use App\Enums\Filing\StatusFilingInvoiceEnum;
use App\Enums\Filing\TypeFilingEnum;
use App\Enums\StatusInvoiceEnum;
use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Filing extends Model
{
    use HasUuids, SoftDeletes,Searchable;

    protected $casts = [
        'type' => TypeFilingEnum::class,
        'status' => StatusFilingEnum::class,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }


    public function filingInvoice(): HasMany
    {
        return $this->hasMany(FilingInvoice::class, 'filing_id');
    }

    public function filingInvoicePreRadicated(): HasMany
    {
        return $this->hasMany(FilingInvoice::class, 'filing_id')->where("status", StatusFilingInvoiceEnum::FILINGINVOICE_EST_001);
    }


    //contar facturas con xml validados
    public function getXmlCountValidateAttribute(): int
    {
        return $this->filingInvoice()->where("status_xml", StatusFilingInvoiceEnum::FILINGINVOICE_EST_003)->count() ?? 0;
    }




    //VERIFICAR SI EXISTEN ERRORES DE VALIDACIÓN
    // Atributo personalizado para verificar errores de validación
    public function getHasValidationErrorsAttribute()
    {
        // Suponiendo que $this->attributes contiene los datos necesarios
        return $this->hasErrors($this->attributes);
    }

    // Función para analizar y verificar si hay errores en un array JSON
    private function parseAndCheckArray($jsonString)
    {
        if ($jsonString) {
            $parsed = json_decode($jsonString, true);
            if ($parsed) {
                return is_array($parsed['errorMessages'] ?? null) && count($parsed['errorMessages']) > 0;
            }
        }
        return false;
    }

    // Función para verificar errores en los diferentes tipos de validación
    private function hasErrors($obj)
    {
        return $this->parseAndCheckArray($obj['validationTxt'] ?? null) ||
            $this->parseAndCheckArray($obj['validationZip'] ?? null);
    }
}
