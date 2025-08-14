<?php

namespace App\Exports\Conciliation;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class ConciliationInvoicesExcelExport implements FromView, ShouldAutoSize, WithEvents
{
    use Exportable;

    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function view(): View
    {
        $data = collect($this->data)->map(function ($value) {
            return [
                'id' => $value->id,
                'invoice_number' => $value->invoiceAudit?->invoice_number,
                'total_value' => formatNumber($value->invoiceAudit?->total_value),
                'origin' => $value->invoiceAudit?->origin,
                'modality' => $value->invoiceAudit?->modality,
                'contract_number' => $value->invoiceAudit?->contract_number,
                'status_description' => $value->conciliation_invoice?->status?->description(),
                'status_backgroundColor' => $value->conciliation_invoice?->status?->backgroundColor(),
                'sum_accepted_value_ips' => formatNumber($value->sum_accepted_value_ips),
                'sum_accepted_value_eps' => formatNumber($value->sum_accepted_value_eps),
                'sum_eps_ratified_value' => formatNumber($value->sum_eps_ratified_value),
            ];
        });

        return view('Conciliation.ConciliationInvoicesExcelExport', ['data' => $data]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Obtener el objeto hoja de cálculo
                $sheet = $event->sheet;

                // Obtener el rango de celdas con datos
                $highestColumn = $sheet->getHighestColumn();
                $highestRow = $sheet->getHighestRow();
                $range = 'A1:'.$highestColumn.$highestRow;

                // Establecer el filtro automático en el rango de celdas
                $sheet->setAutoFilter($range);
            },
        ];
    }
}
