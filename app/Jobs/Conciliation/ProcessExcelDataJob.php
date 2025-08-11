<?php

namespace App\Jobs\Conciliation;

use App\Services\ProcessBatchService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis; // <-- Importar Throwable
use Maatwebsite\Excel\Facades\Excel; // <-- Importar el servicio de progreso
use Throwable;

class ProcessExcelDataJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // Mantener tu timeout

    public $chunkSize = 50; // Puedes ajustar este tamaño de chunk si lo deseas

    public function __construct(
        private string $filePath,
        private int $totalRows // <-- totalRows ya viene aquí, ¡perfecto!
    ) {}

    public function handle()
    {
        if ($this->batch()->cancelled()) {
            return;
        }

        try {
            // 1. Obtener el batch original
            $batch = $this->batch();

            // 2. Crear jobs para cada chunk
            // Pasamos el totalRows que ya tenemos en el constructor
            $chunkJobs = $this->createChunkJobs($batch, $this->totalRows);

            // Si no hay filas de datos (solo encabezados o archivo vacío)
            if (empty($chunkJobs) && $this->totalRows === 0) {
                // Actualizar el estado del batch a completado si no hay filas de datos
                ProcessBatchService::finalizeProcess($this->batch()->id, 0, 0, 'completed');

                return;
            }

            // 3. Agregar jobs al batch existente
            $batch->add($chunkJobs);
        } catch (Throwable $e) {
            Log::error('Error en ProcessExcelDataJob: '.$e->getMessage(), ['exception' => $e]);
            $this->fail($e); // Marcar el job como fallido
            // Actualizar el estado del batch a fallido en la tabla process_batches
            ProcessBatchService::finalizeProcess($this->batch()->id, 0, false, 'failed'); // Usar finalizeProcess para marcar como fallido
        }
    }

    /**
     * Método para crear los jobs de chunk.
     *
     * @param  \Illuminate\Bus\Batch  $batch  El objeto Batch actual.
     * @param  int  $totalRows  El número total de filas de datos en el archivo.
     */
    private function createChunkJobs($batch, int $totalRows): array
    {
        // 1. Leer solo los headers (primera fila)
        $headers = $this->readHeaders($batch->id);

        // 2. Usar el total de filas que ya tenemos
        $chunkSize = $this->chunkSize; // Puedes ajustar este tamaño de chunk si lo deseas
        $jobs = [];

        // 3. Crear jobs basados en el conteo total
        // El bucle debe ir hasta $totalRows + 1 porque startRow es 2 para la primera fila de datos
        for ($startRow = 2; $startRow <= $totalRows + 1; $startRow += $chunkSize) {
            // Calcula el tamaño real del chunk para el último trozo
            $actualChunkSize = min($chunkSize, $totalRows - ($startRow - 2)); // Ajuste para calcular el tamaño real del chunk

            // Solo añadir jobs si hay filas para procesar en este chunk
            if ($actualChunkSize > 0) {
                $jobs[] = new ProcessDataChunkJob(
                    $this->filePath,
                    $startRow,
                    $actualChunkSize,
                    $headers,
                );
            }
        }

        return $jobs;
    }

    /**
     * Lee solo los encabezados del archivo Excel.
     */
    private function readHeaders($batchId): array
    {
        // Obtener el string JSON del header desde Redis
        $headersJson = Redis::get("batch:{$batchId}:headers");

        // Decodificar el string JSON a un array PHP
        $headers = json_decode($headersJson, true);

        return $headers;
    }

    /**
     * Maneja el fallo del job.
     */
    public function failed(Throwable $exception)
    {
        Log::error('ProcessExcelDataJob fallido: '.$exception->getMessage());
        // Actualizar el estado del batch a fallido en la tabla process_batches
        // Se usa finalizeProcess para asegurar que el estado final se registre correctamente.
        ProcessBatchService::finalizeProcess($this->batch()->id, 0, false, 'failed');
    }
}
