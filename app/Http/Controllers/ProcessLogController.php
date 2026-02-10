<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProcessBatchesError\ProcessBatchesErrorPaginateResource;
use App\Models\ProcessBatch;
use App\Repositories\ProcessBatchesErrorRepository;
use App\Traits\HttpResponseTrait;
use Illuminate\Http\Request;

class ProcessLogController extends Controller
{
    use HttpResponseTrait;

    public function __construct(
        protected ProcessBatchesErrorRepository $processBatchesErrorRepository,
    ) {}

    public function paginate(Request $request)
    {
        return $this->execute(function () use ($request) {
            $data = $this->processBatchesErrorRepository->paginate($request->all());
            $tableData = ProcessBatchesErrorPaginateResource::collection($data);

            return [
                'code' => 200,
                'tableData' => $tableData,
                'lastPage' => $data->lastPage(),
                'totalData' => $data->total(),
                'totalPage' => $data->perPage(),
                'currentPage' => $data->currentPage(),
            ];
        });
    }

    public function getUserProcesses(Request $request, $id)
    {
        $processes = ProcessBatch::where('user_id', $id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($batch) {
                $progress = $batch->total_records > 0 ? ($batch->processed_records / $batch->total_records) * 100 : 0;
                if ($batch->status == 'completed' || $batch->status == 'failed') { // Asegurar 100% para completados/fallidos
                    $progress = 100;
                }

                $metadata = json_decode($batch->metadata, true);

                // Determinar current_action basado en el estado
                $currentAction = 'Carga inicial';
                if ($batch->status === 'active') {
                    $currentAction = 'Procesando datos';
                } elseif ($batch->status === 'queued') {
                    $currentAction = 'En cola de espera';
                } elseif ($batch->status === 'completed') {
                    $currentAction = 'Importación finalizada';
                } elseif ($batch->status === 'failed') {
                    $currentAction = 'Importación fallida';
                }

                // Mapear el estado del backend al estado esperado por el frontend
                $frontendStatus = $this->mapBackendStatusToFrontend($batch->status);

                return [
                    'batch_id' => $batch->batch_id,
                    'progress' => round($progress, 2),
                    'current_element' => (string) $batch->processed_records, // Mapear processed_records a current_element
                    'current_action' => $currentAction, // Establecer acción apropiada
                    'status' => $frontendStatus, // Usar el estado mapeado
                    'started_at' => $batch->created_at->toIso8601String(),
                    // completed_at debe establecerse para los estados 'completed' y 'failed'
                    'completed_at' => in_array($batch->status, ['completed', 'failed']) ? $batch->updated_at->toIso8601String() : null,
                    'metadata' => [
                        'total_records' => $batch->total_records,
                        'processed_records' => $batch->processed_records,
                        'errors_count' => $batch->error_count,
                        'processing_start_time' => $batch->created_at->toIso8601String(),
                        'connection_status' => 'disconnected', // Siempre desconectado para carga histórica
                        // Añadir otros campos de metadata si son necesarios por el frontend
                        'file_size' => $metadata['file_size'] ?? 0, // Asumiendo que file_size está en metadata
                        'file_name' => $metadata ? $metadata['file_name'] : 'Archivo desconocido',
                        'current_sheet' => 1, // Valor por defecto para histórico
                        'total_sheets' => 1, // Valor por defecto para histórico
                        'warnings_count' => 0, // Valor por defecto para histórico
                        'processing_speed' => 0, // Valor por defecto para histórico
                        'estimated_time_remaining' => 0, // Valor por defecto para histórico
                    ],
                ];
            });

        return response()->json(['processes' => $processes], 200);
    }

    // Helper function to map backend status to frontend status
    private function mapBackendStatusToFrontend(string $backendStatus): string
    {
        return match ($backendStatus) {
            'active', 'finalizing' => 'active',
            'queued' => 'queued',
            'completed', 'completed_with_errors' => 'completed',
            'failed' => 'error',
            default => 'active', // Default to active if unknown
        };
    }
}
