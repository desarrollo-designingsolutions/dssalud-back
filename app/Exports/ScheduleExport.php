<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class ScheduleExport implements FromView, ShouldAutoSize, WithEvents
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
            $copay = $value->copay ? number_format($value->copay, 0, ',', '.') : 0;

            return [
                'id' => $value->id,
                'patient_fullname' => $value->patient?->fullname,
                'consultory_name' => $value->consultory?->name,
                'day' => $value->day.' '.$value->startHour,
                'user_name' => $value->user?->name,
                'status_name' => $value->status?->name,
                'cie10_name' => $value->cie10?->nombre,
                'copay' => $copay,
                'authorization' => $value->authorization,

            ];
        });

        return view('Exports.Schedule.ScheduleExportExcel', ['data' => $data]);
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
