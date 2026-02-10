<?php

namespace App\Exports\Assignment;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class AssignmentExcelExport implements WithMultipleSheets
{
    use Exportable;

    protected $assignmentBatches;

    protected $users;

    protected $invoiceAudits;

    protected $assignmentStatusEnumValues;

    protected $request;

    public function __construct($assignmentBatches, $users, $invoiceAudits, $assignmentStatusEnumValues, $request)
    {
        $this->assignmentBatches = $assignmentBatches;
        $this->users = $users;
        $this->invoiceAudits = $invoiceAudits;
        $this->assignmentStatusEnumValues = $assignmentStatusEnumValues;
        $this->request = $request;
    }

    public function sheets(): array
    {
        return [
            $this->createSheet('Paquetes', $this->assignmentBatches, 'Exports.Assignment.AssignmentBatchesExcelExport'),
            $this->createSheet('Usuarios', $this->users, 'Exports.Assignment.UsersExcelExport'),
            $this->createSheet('Facturas', $this->invoiceAudits, 'Exports.Assignment.InvoiceAuditExcelExport'),
            $this->createSheet('Estados', $this->assignmentStatusEnumValues, 'Exports.Assignment.StatusAssignmentEnumExcelExport'),
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
