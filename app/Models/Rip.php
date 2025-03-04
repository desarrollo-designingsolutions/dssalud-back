<?php

namespace App\Models;

use App\Enums\StatusRipsEnum;
use App\Events\RipStatusUpdated;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rip extends Model
{
    use HasFactory, HasUuids, SoftDeletes;


    protected $casts = [
        'status' => StatusRipsEnum::class,
    ];


    protected static function booted(): void
    {
        static::saved(function ($rip) {
            if ($rip->isDirty('status')) {
                RipStatusUpdated::dispatch($rip->id);
            }
        });
    }



    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'rip_id', 'id');
    }


    public function user()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function company()
    {
        return $this->hasOne(Company::class, 'id', 'company_id');
    }




    //VERIFICAR SI EXISTEN ERRORES DE VALIDACIÓN
    // Atributo personalizado para verificar errores de validación
    public function getViewBtnErrorAttribute()
    {
        // Suponiendo que $this->attributes contiene los datos necesarios
        return $this->viewBtnErrors($this->attributes);
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
    private function viewBtnErrors($obj)
    {
        return $this->parseAndCheckArray($obj['validationExcel'] ?? null) ||
            $this->parseAndCheckArray($obj['validationTxt'] ?? null) ||
            $this->parseAndCheckArray($obj['validationXml'] ?? null) ||
            $this->parseAndCheckArray($obj['validationZip'] ?? null);
    }
}
