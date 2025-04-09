<?php

namespace App\Exports\Service;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class ServiceListExcelExport implements WithMultipleSheets
{
    use Exportable;

    protected $services;

    public function __construct($services)
    {
        $this->services = $services;
    }

    public function sheets(): array
    {
        return [
            $this->createSheet('Servicios', $this->services, 'Exports.InvoiceAudit.InvoiceAuditExcelServicesExport'),
        ];
    }

    protected function createSheet($title, $data, $view)
    {
        return new class($title, $data, $view) implements FromView, ShouldAutoSize, WithEvents, WithTitle
        {
            protected $title;

            protected $data;

            protected $view;

            public function __construct($title, $data, $view)
            {
                $this->title = $title;
                $this->data = $data;
                $this->view = $view;
            }

            public function view(): View
            {
                return \Illuminate\Support\Facades\View::make($this->view, ['data' => $this->data]);
            }

            public function title(): string
            {
                return $this->title;
            }

            public function registerEvents(): array
            {
                return [
                    AfterSheet::class => function (AfterSheet $event) {
                        $sheet = $event->sheet;
                        $highestColumn = $sheet->getHighestColumn();
                        $highestRow = $sheet->getHighestRow();
                        $range = 'A1:'.$highestColumn.$highestRow;
                        $sheet->setAutoFilter($range);
                    },
                ];
            }
        };
    }
}
