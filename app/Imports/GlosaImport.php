<?php

namespace App\Imports;

// app/Imports/AssingmentImport.php

use App\Events\ProgressCircular;
use App\Helpers\Constants;
use App\Models\Glosa;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Concerns\ToModel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Redis;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class GlosaImport implements ToModel, WithChunkReading, ShouldQueue, WithEvents
{
    public function __construct(
        protected $user_id,
    ) {}


    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function (BeforeImport $event) {
                // Obtener total de filas (ajusta si hay encabezados)
                $totalRows = $event->getReader()->getTotalRows()['Worksheet'];
                $totalRows = max($totalRows, 1);

                Redis::set("integer:glosas_import_total_{$this->user_id}", $totalRows);
                Redis::set("integer:glosas_import_processed_{$this->user_id}", 0);
            },
            AfterImport::class => function (AfterImport $event) {
                // Limpiar cache al finalizar

                Redis::del("integer:glosas_import_total_{$this->user_id}");
                Redis::del("integer:glosas_import_processed_{$this->user_id}");
            }
        ];
    }

    public function model(array $row)
    {
        // Incrementar contador y calcular progreso

        $processed = Redis::incrby("integer:glosas_import_processed_{$this->user_id}", 1);
        $total = Redis::get("integer:glosas_import_total_{$this->user_id}") ?: 1;
        $progress = ($processed / $total) * 100;

        // Emitir evento de progreso
        ProgressCircular::dispatch("glosa.{$this->user_id}", $progress);

        sleep(3);
        return Glosa::create(
            [
                'user_id' => $row[0],
                'service_id' => $row[1],
                'code_glosa_id' => $row[2],
                'glosa_value' => $row[3],
                'observation' => $row[4],
            ]
        );
    }

    public function chunkSize(): int
    {
        return Constants::CHUNKSIZE; // Aumenta este valor para mejor rendimiento
    }
}
