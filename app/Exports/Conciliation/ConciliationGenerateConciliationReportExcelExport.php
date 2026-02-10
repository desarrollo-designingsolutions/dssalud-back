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
use Illuminate\Support\Facades\Redis;

class ConciliationGenerateConciliationReportExcelExport implements FromView, ShouldAutoSize, WithEvents, WithDrawings
{
    use Exportable;

    public $data;
    public $invoicesKey;

    public function __construct($data)
    {
        $this->data = $data;
        $this->invoicesKey = $data['redis_invoices_key'] ?? null;
    }

    public function view(): View
    {
        // Leer invoices de Redis si está disponible
        if ($this->invoicesKey) {
            $redis = Redis::connection();
            $invoices = $redis->lrange($this->invoicesKey, 0, -1);

            $this->data['invoices'] = array_map(function($item) {
                return json_decode($item, true);
            }, $invoices);
        }

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
