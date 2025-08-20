<?php

namespace App\Exports\Conciliation;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithDrawings;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class ConciliationGenerateConciliationReportExcelExport implements FromView, ShouldAutoSize, WithEvents, WithDrawings
{
    use Exportable;

    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function view(): View
    {


        return view('Conciliation.ConciliationGenerateConciliationReportExcelExport', ['data' => $this->data]);
    }

    /**
     * Define los eventos después de generar la hoja
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Forzar ancho de columna A y evitar autoajuste
                $column = $event->sheet->getDelegate()->getColumnDimension('A');
                $column->setWidth(20);
                $column->setAutoSize(false);
            }
        ];
    }

    public function drawings()
    {
        $drawing = new Drawing();
        $drawing->setName('Logo');
        $drawing->setPath(public_path('/images/logo_cosalud.png'));
        $drawing->setHeight(30);
        $drawing->setCoordinates('B2');

        return $drawing;
    }
}
