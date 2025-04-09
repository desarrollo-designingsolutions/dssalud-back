<?php

namespace App\Imports;

// app/Imports/AssingmentImport.php

use App\Events\ProgressCircular;
use App\Helpers\Constants;
use App\Models\Assignment;
use App\Services\CacheService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Redis;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\BeforeImport;

class AssingmentImport implements ShouldQueue, ToModel, WithChunkReading, WithEvents
{
    // ... constructor y otras propiedades ...

    public function __construct(
        protected $user_id,
        protected $company_id,
    ) {
        $cache = new CacheService;
    }

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function (BeforeImport $event) {
                // Obtener total de filas (ajusta si hay encabezados)
                $totalRows = $event->getReader()->getTotalRows()['Worksheet'];
                $totalRows = max($totalRows, 1);

                Redis::set("integer:assignments_import_total_{$this->user_id}", $totalRows);
                Redis::set("integer:assignments_import_processed_{$this->user_id}", 0);
            },
            AfterImport::class => function (AfterImport $event) {
                // Limpiar cache al finalizar

                Redis::del("integer:assignments_import_total_{$this->user_id}");
                Redis::del("integer:assignments_import_processed_{$this->user_id}");
            },
        ];
    }

    public function model(array $row)
    {
        // Incrementar contador y calcular progreso

        $processed = Redis::incrby("integer:assignments_import_processed_{$this->user_id}", 1);
        $total = Redis::get("integer:assignments_import_total_{$this->user_id}") ?: 1;
        $progress = ($processed / $total) * 100;

        // Emitir evento de progreso
        ProgressCircular::dispatch("assignment.{$this->user_id}", $progress);

        return Assignment::create(
            [
                'assignment_batch_id' => $row[0],
                'user_id' => $row[1],
                'invoice_audit_id' => $row[2],
                'phase' => $row[3],
                'status' => $row[4],
            ]
        );
    }

    public function chunkSize(): int
    {
        return Constants::CHUNKSIZE; // Aumenta este valor para mejor rendimiento
    }
}
