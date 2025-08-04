<?php

namespace App\Events;

use Carbon\Carbon;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log; // Importar Carbon para cálculos de tiempo
use Illuminate\Support\Facades\Redis; // Importar Redis

class ImportProgressEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    // Propiedades que coinciden directamente con la interfaz ImportProcess
    public string $batchId;

    public string $fileName;

    public float $progress; // Porcentaje de progreso

    public string $currentStudent; // Mapeado desde current_element (número de registro)

    public string $currentAction;

    public string $status; // Mapeado a los estados del frontend

    // Propiedad para el objeto 'metadata' anidado
    public array $metadata;

    /**
     * Constructor del evento de progreso.
     *
     * @param  string  $batchId  El ID del batch.
     * @param  int  $processedRecords  El número de registros procesados hasta el momento.
     * @param  string  $currentAction  La acción actual que se está realizando (ej. "Procesando datos").
     * @param  string  $errorCount  El conteo actual de errores.
     * @param  string  $backendStatus  El estado del batch desde la lógica del backend (ej. 'active', 'failed').
     * @param  string  $currentElement  El número de fila/registro actual que se está procesando.
     */
    public function __construct(
        string $batchId,
        string $processedRecords,
        string $currentAction,
        string $errorCount,
        string $backendStatus,
        string $currentElement
    ) {
        $this->batchId = $batchId;
        $this->currentAction = $currentAction;
        $this->currentStudent = (string) $currentElement; // El frontend espera un string

        // --- INICIO DE CAMBIOS ---

        // 1. Leer metadatos estáticos del hash de Redis
        $staticMetadata = Redis::hgetall("batch:{$this->batchId}:metadata");

        // Asegurar valores por defecto si no se encuentran en Redis
        $totalRecords = (int) ($staticMetadata['total_rows'] ?? 0);
        $this->fileName = $staticMetadata['file_name'] ?? 'N/A';
        $started_at = $staticMetadata['started_at'] ?? now()->toDateTimeString();
        $completed_at = $staticMetadata['completed_at'] ?? 'N/A';

        // Calcular el porcentaje de progreso
        $this->progress = $totalRecords > 0 ? round(($processedRecords / $totalRecords) * 100, 2) : 0;

        // Mapear el estado del backend al estado esperado por el frontend
        $this->status = $this->mapStatus($backendStatus);

        // Preparar el objeto 'metadata'
        $this->metadata = [
            'total_records' => $totalRecords,
            'processed_records' => $processedRecords,
            'errors_count' => $errorCount,
            'last_activity' => now()->toDateTimeString(),
            'started_at' => $started_at,
            'completed_at' => $completed_at,
            'file_size' => (int) ($staticMetadata['file_size'] ?? 0),
            'current_sheet' => (int) ($staticMetadata['current_sheet'] ?? 1),
            'total_sheets' => (int) ($staticMetadata['total_sheets'] ?? 1),
            'connection_status' => 'connected', // Asumimos conectado si el evento se dispara
        ];

        if ($started_at) {
            try {
                $startTime = Carbon::parse($started_at);
                // CORRECCIÓN: Asegurar que elapsedSeconds sea siempre positivo y al menos 1
                $elapsedSeconds = max(1, abs(Carbon::now()->diffInSeconds($startTime, false)));

                // La condición processedRecords > 0 es suficiente aquí, ya que elapsedSeconds ya es > 0
                if ($processedRecords > 0) {
                    $processingSpeed = round($processedRecords / $elapsedSeconds, 2);
                    $this->metadata['processing_speed'] = $processingSpeed;

                    $remainingRecords = $totalRecords - $processedRecords;

                    if ($processingSpeed > 0 && $remainingRecords > 0) {
                        $estimatedTimeRemaining = round($remainingRecords / $processingSpeed);
                        $this->metadata['estimated_time_remaining'] = $estimatedTimeRemaining;
                    } else {
                    }
                } else {
                }
            } catch (\Exception $e) {
                Log::warning('Error calculando métricas de progreso: '.$e->getMessage());
            }
        } else {
        }
    }

    /**
     * Mapea los estados internos del backend a los estados esperados por el frontend.
     */
    protected function mapStatus(string $backendStatus): string
    {
        return match ($backendStatus) {
            'active', 'finalizing' => 'active',
            'queued' => 'queued',
            'completed', 'completed_with_errors' => 'completed',
            'failed' => 'error',
            default => 'active', // Valor por defecto si el estado no es reconocido
        };
    }

    public function broadcastOn(): Channel
    {
        return new Channel('import.progress.'.$this->batchId);
    }

    public function broadcastAs(): string
    {
        return 'progress.update';
    }

    /**
     * Prepara los datos que serán transmitidos a través del WebSocket.
     */
    public function broadcastWith(): array
    {
        return [
            'batch_id' => $this->batchId,
            'file_name' => $this->fileName,
            'progress' => $this->progress,
            'current_student' => $this->currentStudent,
            'current_action' => $this->currentAction,
            'status' => $this->status,
            'metadata' => $this->metadata,
        ];
    }
}
