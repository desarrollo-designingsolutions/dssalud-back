<?php

namespace App\Exports\InvoiceAudit;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class InvoiceAuditExcelExport implements WithMultipleSheets
{
    use Exportable;

    protected $services;

    protected $glosses;

    protected $attachedData;

    protected $request;

    public function __construct($services, $glosses, $attachedData, $request)
    {
        $this->services = $services;
        $this->glosses = $glosses;
        $this->attachedData = $attachedData;
        $this->request = $request;
    }

    public function sheets(): array
    {
        return [
            $this->createSheet('Servicios', $this->services, 'Exports.InvoiceAudit.InvoiceAuditExcelServicesExport'),
            $this->createSheet('Codigo de Glosas', $this->glosses, 'Exports.InvoiceAudit.InvoiceAuditExcelGlossesExport'),
            $this->createSheet('Datos Anexos', $this->attachedData, 'Exports.InvoiceAudit.InvoiceAuditExcelAttachedDataExport'),
        ];
    }

    protected function createSheet($title, $data, $view)
    {
        $request = $this->request;

        return new class($title, $data, $view, $request) implements FromView, ShouldAutoSize, WithEvents, WithTitle
        {
            protected $title;

            protected $data;

            protected $view;

            protected $request;

            public function __construct($title, $data, $view, $request)
            {
                $this->title = $title;
                $this->data = $data;
                $this->view = $view;
                $this->request = $request;
            }

            public function view(): View
            {
                return \Illuminate\Support\Facades\View::make($this->view, [
                    'data' => $this->data,
                    'request' => $this->request,
                ]);
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
