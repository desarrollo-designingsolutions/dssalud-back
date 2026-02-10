<?php

namespace App\Exports\Schedule;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class ScheduleAgendaListExport implements FromView, ShouldAutoSize, WithEvents
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

            $response_date = $value->response_date ? Carbon::parse($value->response_date)->format('d-m-Y H:i') : 'Sin respuesta';

            return [
                'id' => $value->id,
                'title' => $value->title,
                'response_status_backgroundColor' => $value->scheduleable->response_status?->backgroundColor(),
                'response_status_description' => $value->scheduleable->response_status?->description(),
                'response_date' => $response_date,

                'user_name' => $value->scheduleable?->user?->full_name,
                'third_name' => $value->scheduleable?->third?->nit.' - '.$value->scheduleable?->third?->name,
                'reconciliation_group_name' => $value->scheduleable?->reconciliation_group?->name,

            ];
        });

        return view('Exports.Schedule.ScheduleAgendaListExportExcel', ['data' => $data]);
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
