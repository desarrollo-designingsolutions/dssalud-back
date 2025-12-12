<?php

namespace App\Jobs;

use App\Helpers\Constants;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use App\Models\ReconciliationGroupInvoice;
use App\Models\User;
use App\Notifications\BellNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CreateConciliationExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $request;
    protected $userId;
    protected $fileName;

    public function __construct($request, $userId, $fileName)
    {
        $this->request = $request;
        $this->userId = $userId;
        $this->fileName = $fileName;
    }

    public function handle()
    {
        // Primero obtenemos el conteo total
        $totalCount = ReconciliationGroupInvoice::when(
            !empty($this->request['reconciliation_group_id']),
            fn($q) => $q->where('reconciliation_group_id', $this->request['reconciliation_group_id'])
        )->count();
        // Log::info("totalCount", [$totalCount]);

        $chunkSize = 500; // Tamaño de cada chunk
        $chunks = ceil($totalCount / $chunkSize);
        $tempFileName = 'conciliation_' . now()->format('Ymd_His') . '.csv';

        // Crear encabezados
        $headers = [
            'Número de factura',
            'Total',
            'Origen',
            'Modalidad',
            'Número de contacto',
            'Estado',
            'Suma valor ips',
            'Suma valor eps',
            'Suma valor eps ratificado'
        ];

        Storage::disk(Constants::DISK_FILES)->put('temp/exports/' . $tempFileName, implode(',', $headers) . "\n");

        // Crear batch de jobs
        $jobs = [];
        for ($i = 0; $i < $chunks; $i++) {
            $offset = $i * $chunkSize;
            $cleanRequest = $this->cleanRequest($this->request);

            $jobs[] = new ProcessConciliationChunk(
                $cleanRequest, // Datos limpios
                $offset,
                $chunkSize,
                $tempFileName
            );
        }

        $fileName = $this->fileName;
        $userId = $this->userId;
        // Ejecutar batch
        $batch = Bus::batch($jobs)
            ->name('conciliation_export')
            ->onqueue('download_files')
            ->catch(function (Throwable $e) {
                Log::error('Error en el batch: ' . $e->getMessage());
            })
            ->finally(function () use ($tempFileName, $fileName, $userId) {
                // Cuando todos los chunks estén listos, podemos:
                // 1. Convertir el CSV a Excel si es necesario
                // 2. Mover el archivo a la ubicación final
                // 3. Notificar al usuario

                $finalPath = 'exports/' . $fileName;
                Storage::disk(Constants::DISK_FILES)->move('temp/exports/' . $tempFileName, $finalPath);


                // Obtener URL completa para descarga
                $absolutePath = env('SYSTEM_URL_BACK') . 'storage/' . $finalPath;
                // Ejemplo resultado: "http://tudominio.com/storage/exports/archivo.xlsx"



                // Log::info("finalizo");

                $user = User::select("id")->find($userId);
                $data =  [
                    'title' => "Listado de facturas procesado con éxito",
                    'subtitle' => "Da click en la notificación para descargar",
                    'action_url' => $absolutePath,
                    'openInNewTab' => true,
                ];

                // Log::info("user", [$user]);
                // Log::info("data", [$data]);

                $user->notify(new BellNotification($data));
            })
            ->dispatch();

        return $batch;
    }

    // Añade este método en CreateConciliationExport
    protected function cleanRequest(array $request): array
    {
        // Elimina elementos que no sean serializables
        return collect($request)
            ->reject(fn($item) => is_object($item) && !method_exists($item, '__serialize'))
            ->toArray();
    }
}
