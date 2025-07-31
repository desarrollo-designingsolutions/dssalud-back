<?php

namespace App\Http\Controllers;

use App\Events\ImportProgressEvent;
use App\Exports\Conciliation\ConciliationExcelExport;
use App\Exports\Conciliation\ConciliationInvoicesExcelExport;
use App\Helpers\Constants;
use App\Http\Requests\Conciliation\ConciliationUploadFileRequest;
use App\Http\Resources\Conciliation\ConciliationInvoicePaginateResource;
use App\Http\Resources\Conciliation\ConciliationPaginateResource;
use App\Http\Resources\Conciliation\ConciliationShowResource;
use App\Models\ReconciliationGroupInvoice;
use App\Repositories\ReconciliationGroupInvoiceRepository;
use App\Repositories\ReconciliationGroupRepository;
use App\Services\Conciliation\ExcelStructureValidator;
use App\Services\Conciliation\ExcelConciliationProcessor;
use App\Services\ProcessBatchService;
use App\Traits\HttpResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;
use Maatwebsite\Excel\Facades\Excel;

class ConciliationController extends Controller
{
    use HttpResponseTrait;

    public function __construct(
        protected ReconciliationGroupRepository $reconciliationGroupRepository,
        protected ReconciliationGroupInvoiceRepository $reconciliationGroupInvoiceRepository,
        protected ExcelStructureValidator $excelStructureValidator,
        protected ExcelConciliationProcessor $excelConciliationProcessor,
    ) {}


    public function paginateConciliation(Request $request)
    {
        return $this->execute(function () use ($request) {
            $data = $this->reconciliationGroupRepository->paginateConciliation($request->all());
            $tableData = ConciliationPaginateResource::collection($data);

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

    public function show($id)
    {
        try {
            $reconciliationGroup = $this->reconciliationGroupRepository->find($id);
            $form = new ConciliationShowResource($reconciliationGroup);

            return response()->json([
                'code' => 200,
                'form' => $form,
            ]);
        } catch (Throwable $th) {

            return response()->json(['code' => 500, $th->getMessage(), $th->getLine()]);
        }
    }

    public function excelExportConciliation(Request $request)
    {
        return $this->execute(function () use ($request) {
            $request['typeData'] = 'all';

            $data = $this->reconciliationGroupRepository->paginate($request->all());

            $excel = Excel::raw(new ConciliationExcelExport($data), \Maatwebsite\Excel\Excel::XLSX);

            $excelBase64 = base64_encode($excel);

            return [
                'code' => 200,
                'excel' => $excelBase64,
            ];
        });
    }

    public function paginateConciliationInvoices(Request $request)
    {
        return $this->execute(function () use ($request) {
            $data = $this->reconciliationGroupInvoiceRepository->paginateConciliationInvoices($request->all());
            $tableData = ConciliationInvoicePaginateResource::collection($data);

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

    public function uploadFile(ConciliationUploadFileRequest $request)
    {
        $company_id = $request->input('company_id');
        $user_id = $request->input('user_id');

        // $fullPath = public_path('Libro1.xlsx');
        $uploadedFile = $request->file('file');

        // Guardar archivo temporalmente
        $fileName = time() . '_' . $uploadedFile->getClientOriginalName();
        $filePath = $uploadedFile->storeAs('temp', $fileName, Constants::DISK_FILES);
        $fullPath = storage_path('app/public/' . $filePath);

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
                $company_id,
                $user_id,
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


    public function getErrors(string $batchId)
    {
        try {
            $errors = Cache::get("conciliation_errors_{$batchId}");

            if (!$errors) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se encontraron errores para este batch o ya expiraron'
                ], 404);
            }

            $decodedErrors = $errors; // Ya no necesita json_decode
            $errorCount = count($decodedErrors);

            $recordsWithErrors = count(array_unique(array_column($decodedErrors, 'row')));

            return response()->json([
                'status' => 'success',
                'total_errors' => $errorCount,
                'records_with_errors' => $recordsWithErrors,
                'errors' => $decodedErrors,
                'count' => $errorCount,
                'cache_driver' => config('cache.default') // Info adicional
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al recuperar errores: ' . $e->getMessage()
            ], 500);
        }
    }

    public function showErrors(string $batchId, Request $request)
    {
        $page = $request->get('page', 1);
        $perPage = min($request->get('per_page', 200), 500); // Máximo 500 por página

        try {
            $result = ProcessBatchService::getErrors($batchId, $page, $perPage);

            return response()->json([
                'success' => true,
                'data' => $result['data'],
                'meta' => $result['meta']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar los errores'
            ], 500);
        }
    }

    public function excelExportConciliationInvoices(Request $request)
    {
        return $this->execute(function () use ($request) {
            $request['typeData'] = 'all';

            $data = $this->reconciliationGroupInvoiceRepository->paginateConciliationInvoices($request->all());

            $excel = Excel::raw(new ConciliationInvoicesExcelExport($data), \Maatwebsite\Excel\Excel::XLSX);

            $excelBase64 = base64_encode($excel);

            return [
                'code' => 200,
                'excel' => $excelBase64,
            ];
        });
    }
}
