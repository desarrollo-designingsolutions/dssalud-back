<?php

namespace App\Exports\Glosa;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

class GlosaExcelErrorsValidationExport implements FromCollection, WithCustomCsvSettings
{
    use Exportable;

    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        // Convertir $this->data en una colección y extraer solo los valores
        return collect($this->data)->map(function ($item) {
            // Si $item es un arreglo asociativo, devolver solo los valores
            return array_values($item);
        });
    }

    public function getCsvSettings(): array
    {
        return [
            'delimiter' => ';',         // Separador: punto y coma
            'enclosure' => '"',          // Con comillas alrededor de los valores
            'escape_character' => '\\', // Carácter de escape (por si acaso)
            'input_encoding' => 'UTF-8',
            'output_encoding' => 'UTF-8',
        ];
    }
}
