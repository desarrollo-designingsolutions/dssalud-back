<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ExcelToCsvExporter implements FromCollection, WithCustomCsvSettings, WithHeadings
{
    protected $dataCollection;

    protected $originalHeaders;

    /**
     * @param  Collection  $dataCollection  La colección de datos a exportar (sin la fila de encabezados).
     * @param  array  $originalHeaders  Las cabeceras exactas del archivo original.
     */
    public function __construct(Collection $dataCollection, array $originalHeaders)
    {
        $this->dataCollection = $dataCollection;
        $this->originalHeaders = $originalHeaders;
    }

    /**
     * Retorna la colección de datos que se exportará.
     */
    public function collection()
    {
        // Asegurar que los valores 0 no se pierdan
        return $this->dataCollection->map(function ($row) {
            return array_map(function ($value) {
                return $value === null ? '' : $value; // Preserva 0, convierte null a cadena vacía
            }, $row);
        });
    }

    /**
     * Define las cabeceras que se escribirán en la primera fila del CSV.
     * Estas serán las cabeceras originales del Excel.
     */
    public function headings(): array
    {
        return $this->originalHeaders;
    }

    /**
     * Define la configuración personalizada para la exportación a CSV.
     * Usamos ';' como delimitador para que sea compatible con tu lógica de importación CSV.
     */
    public function getCsvSettings(): array
    {
        return [
            'delimiter' => ';',
            'enclosure' => '"',
            'line_ending' => PHP_EOL,
            'use_bom' => true, // Cambiado a true para mejor compatibilidad
            'excel_compatibility' => false,
            'include_separator_line' => false,
            'escape_character' => '\\',
            'contiguous' => true, // Para manejar celdas vacías correctamente
        ];
    }
}
