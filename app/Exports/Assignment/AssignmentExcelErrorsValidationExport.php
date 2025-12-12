<?php

namespace App\Exports\Assignment;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AssignmentExcelErrorsValidationExport implements FromCollection, WithCustomCsvSettings, WithHeadings
{
    use Exportable;

    public $data;

    protected $removeLastColumn;

    protected $includeHeadings;

    public function __construct($data, $removeLastColumn = false, $includeHeadings = false)
    {
        $this->data = $data;
        $this->removeLastColumn = $removeLastColumn;
        $this->includeHeadings = $includeHeadings;
    }

    public function collection()
    {
        return collect($this->data)->map(function ($item) {
            $values = array_values($item);
            if ($this->removeLastColumn) {
                array_pop($values); // Eliminar la última columna
            }

            return $values;
        });
    }

    public function headings(): array
    {
        if ($this->includeHeadings) {
            return ['columna', 'fila', 'valor', 'mensaje de error'];
        }

        return [];
    }

    public function getCsvSettings(): array
    {
        return [
            'delimiter' => ';',
            'enclosure' => '', // Evita las comillas alrededor de los valores
            'input_encoding' => 'UTF-8',
        ];
    }
}
