<?php

namespace App\Http\Controllers;

use App\Events\ImportProgressEvent;
use App\Exports\ReconciliationGroup\ReconciliationGroupExcelExport;
use App\Helpers\Constants;
use App\Http\Requests\Conciliation\ConciliationUploadFileRequest;
use App\Http\Requests\ReconciliationGroup\ReconciliationGroupStoreRequest;
use App\Http\Resources\ReconciliationGroup\ReconciliationGroupFormResource;
use App\Http\Resources\ReconciliationGroup\ReconciliationGroupPaginateResource;
use App\Repositories\ReconciliationGroupRepository;
use App\Services\Conciliation\ExcelStructureValidator;
use App\Services\Conciliation\ExcelConciliationProcessor;
use App\Traits\HttpResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;
use Maatwebsite\Excel\Facades\Excel;


class ConciliationController extends Controller
{
    use HttpResponseTrait;

    public function __construct(
        protected ExcelStructureValidator $excelStructureValidator,
        protected ExcelConciliationProcessor $excelConciliationProcessor,
    ) {}

    public function uploadFile(ConciliationUploadFileRequest $request)
    {
        $company_id = $request->input('company_id', '9e5aec58-a962-4670-8188-b41c6d0149a3');

        $fullPath = public_path('Libro1.xlsx');
        // $uploadedFile = $request->file('file');

        // // Guardar archivo temporalmente
        // $fileName = time() . '_' . $uploadedFile->getClientOriginalName();
        // $filePath = $uploadedFile->storeAs('temp', $fileName, Constants::DISK_FILES);
        // $fullPath = storage_path('app/public/' . $filePath);

        try {
            Log::info("🔍 [CONTROLLER] Starting validation for: {$fullPath}");

            // Validación rápida
            $validation = $this->excelStructureValidator->validate(
                $fullPath
            );

            if ($validation['operation_failed']) {
                // Storage::disk(Constants::DISK_FILES)->delete($filePath);
                Log::warning("❌ [CONTROLLER] Validation failed for: {$fullPath}");

                return response()->json([
                    'status' => 'error',
                    'message' => 'Errores en la validación',
                    'errors' => $validation['data']
                ], 422);
            }

            Log::info("✅ [CONTROLLER] Validation successful, starting processing for: {$fullPath}");

            // Procesamiento asíncrono
            $result = $this->excelConciliationProcessor->processFile(
                $fullPath,
                $company_id
            );

            if (!$result['success']) {
                // Storage::disk(Constants::DISK_FILES)->delete($filePath);
                Log::error("❌ [CONTROLLER] Processing error for: {$fullPath} - {$result['error']}");

                return response()->json([
                    'status' => 'error',
                    'message' => $result['error']
                ], 500);
            }

            // Inicializar progreso en cache
            Cache::put("batch_processed_{$result['batch_id']}", 0, now()->addHours(2));

            Log::info("🎯 [CONTROLLER] Batch created successfully: {$result['batch_id']} for file: {$fullPath}");

            // ✅ EMITIR EVENTO INICIAL CON METADATA COMPLETA
            event(new ImportProgressEvent(
                $result['batch_id'],
                0,
                'Iniciando proceso',
                'Validando estructura',
                [
                    'sheet' => 0,
                    'chunk' => 0,
                    'current_row' => 0,
                    'total_rows' => 0,
                    'total_sheets' => $result['total_sheets'],
                    'total_chunks' => $result['total_chunks'],
                    'total_records' => $result['total_records'],
                    'processed_records' => 0,
                    'general_progress' => 0,
                    'connection_type' => 'websocket',
                    // ✅ NUEVOS DATOS INICIALES
                    'current_sheet' => 1,
                    'errors_count' => 0,
                    'warnings_count' => 0,
                    'file_size' => $result['file_size'] ?? 0,
                    'processing_start_time' => $result['processing_start_time'] ?? now()->toDateTimeString(),
                    'last_activity' => now()->toDateTimeString(),
                    'memory_usage' => memory_get_usage(true),
                    'cpu_usage' => 0,
                    'connection_status' => 'connected',
                ]
            ));

            Log::info("📤 [CONTROLLER] Sending immediate response for batch: {$result['batch_id']}");

            return response()->json([
                'status' => 'success',
                'batch_id' => $result['batch_id'],
                'sheets' => $result['total_sheets'],
                'chunks' => $result['total_chunks'],
                'total_records' => $result['total_records'],
                // 'file_name' => $uploadedFile->getClientOriginalName(),
                'file_size' => $result['file_size'] ?? 0,
                'processing_start_time' => $result['processing_start_time'] ?? now()->toDateTimeString(),
                // 'message' => 'Archivo enviado a procesamiento. El progreso se actualizará via WebSocket.'
            ], 200);
        } catch (\Exception $e) {
            // if (Storage::disk(Constants::DISK_FILES)->exists($filePath)) {
            //     Storage::disk(Constants::DISK_FILES)->delete($filePath);
            // }

            Log::error("💥 [CONTROLLER] Exception during processing: {$e->getMessage()}", [
                // 'file' => $fileName,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error procesando el archivo: ' . $e->getMessage()
            ], 500);
        }
    }
}
