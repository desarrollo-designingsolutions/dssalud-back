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
use App\Jobs\Conciliation\FinalizeImportDecisionJob;
use App\Jobs\Conciliation\ProcessExcelDataJob;
use App\Jobs\Conciliation\ValidateExcelStructureJob;
use App\Models\ProcessBatch;
use App\Repositories\ReconciliationGroupInvoiceRepository;
use App\Repositories\ReconciliationGroupRepository;
use App\Services\Conciliation\ExcelStructureValidator;
use App\Services\ProcessBatchService;
use App\Traits\HttpResponseTrait;
use Illuminate\Bus\Batch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ConciliationController extends Controller
{
    use HttpResponseTrait;

    public function __construct(
        protected ReconciliationGroupRepository $reconciliationGroupRepository,
        protected ReconciliationGroupInvoiceRepository $reconciliationGroupInvoiceRepository,
        protected ExcelStructureValidator $excelStructureValidator,
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
        $uploadedFile = $request->file('file');

        // Guardar archivo temporalmente
        $fileName = time().'_'.$uploadedFile->getClientOriginalName();
        $filePath = $uploadedFile->storeAs('temp', $fileName, Constants::DISK_FILES);
        $fullPath = storage_path('app/public/'.$filePath);

        // Obtener nombre y tamaño del archivo para metadatos
        $originalFileName = $uploadedFile->getClientOriginalName();
        $fileSize = $uploadedFile->getSize();

        // Obtener el total de filas del archivo Excel (excluyendo encabezado)
        $totalRows = Excel::toCollection(new \stdClass, $fullPath)[0]->count() - 1;
        if ($totalRows < 0) {
            $totalRows = 0;
        }

        // Definir un conjunto de colas disponibles
        $availableQueues = [
            'imports_1',
            'imports_2',
            'imports_3',
            'imports_4',
            'imports_5',
        ];

        $selectedQueue = null; // Inicializar a null

        try {
            // 1. Seleccionar una cola disponible ANTES de despachar el batch
            $selectedQueue = ProcessBatchService::selectAvailableQueue($availableQueues);
        } catch (Throwable $e) {
            // Si no se puede seleccionar una cola, responder con error y limpiar el archivo temporal
            if (Storage::exists($fullPath)) {
                Storage::delete($fullPath);
            }

            return response()->json([
                'message' => 'Error: '.$e->getMessage(),
                'error' => $e->getMessage(),
            ], 503); // 503 Service Unavailable, ya que no hay recursos de cola
        }

        // Crear batch con dos jobs secuenciales
        $initialJobs = [
            new ValidateExcelStructureJob($fullPath),
            new ProcessExcelDataJob($fullPath, $totalRows), // <-- totalRows se pasa aquí
        ];

        $batch = Bus::batch($initialJobs)
            ->name('ProcessConciliation_'.now()->format('Y-m-d_H-i-s'))
            ->onQueue($selectedQueue)
            ->allowFailures()
            ->before(function (Batch $batch) use ($fullPath, $totalRows, $originalFileName, $fileSize) {

                // Guardar totalRows en Redis usando tu ID temporal
                Redis::set("process:{$batch->id}:total_rows", $totalRows);

                // Leer solo la primera fila (headers)
                $import = new \App\Imports\ChunkDataImport(1, 1);
                $data = Excel::toArray($import, $fullPath)[0];
                Redis::set("batch:{$batch->id}:headers", json_encode($data[0] ?? []));

                // Guardar metadatos estáticos en Redis como un hash
                Redis::hmset("batch:{$batch->id}:metadata", [
                    'total_rows' => $totalRows,
                    'file_name' => $originalFileName,
                    'file_size' => $fileSize,
                    'started_at' => now()->toDateTimeString(),
                    'completed_at' => null,
                    'total_sheets' => 1, // Asumiendo una sola hoja
                    'current_sheet' => 1, // Asumiendo una sola hoja
                ]);

                // Emitir evento de progreso inicial
                event(new ImportProgressEvent(
                    $batch->id, // Usar el batch ID real de Laravel
                    0, // processedRecords
                    'Iniciando proceso de importación',
                    0, // errorCount
                    'active', // backendStatus
                    0, // currentElement
                ));
            })
            ->then(function (Batch $batch) use ($fullPath) {
                FinalizeImportDecisionJob::dispatch($batch->id, $fullPath);
            })
            ->catch(function (Batch $batch, Throwable $e) use ($fullPath) {

                $redisKey = "batch:{$batch->id}:errors";
                $errorsFromRedis = Redis::lrange($redisKey, 0, -1);
                $currentErrorCount = count($errorsFromRedis);

                if (! empty($errorsFromRedis)) {
                    $errorsToInsert = [];
                    foreach ($errorsFromRedis as $errorJson) {
                        $errorData = json_decode($errorJson, true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            continue;
                        }
                        $errorsToInsert[] = [
                            'id' => Str::uuid(),
                            'batch_id' => $batch->id,
                            'row_number' => $errorData['row_number'] ?? null,
                            'column_name' => $errorData['column_name'] ?? null,
                            'error_message' => $errorData['error_message'] ?? 'Error desconocido',
                            'error_type' => $errorData['error_type'] ?? 'unknown',
                            'original_data' => json_encode($errorData['original_data'] ?? null),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    if (! empty($errorsToInsert)) {
                        try {
                            DB::table('process_batche_errors')->insert($errorsToInsert);
                        } catch (Throwable $dbE) {
                        }
                    }
                }

                $processBatchRecord = ProcessBatch::where('batch_id', $batch->id)->first();
                $processedRecordsAtFailure = $processBatchRecord ? $processBatchRecord->processed_records : 0;
                ProcessBatchService::finalizeProcess($batch->id, $currentErrorCount, false, 'failed');

                event(new ImportProgressEvent(
                    $batch->id,
                    $processedRecordsAtFailure,
                    'Se encontraron errores en la estructura del archivo',
                    $currentErrorCount,
                    'failed', // Backend status
                    $processedRecordsAtFailure, // currentElement
                ));

                Redis::del("batch:{$batch->id}:errors");

                if (Storage::exists($fullPath)) {
                    Storage::delete($fullPath);
                }
            })
            ->finally(function (Batch $batch) use ($selectedQueue) {

                ProcessBatchService::releaseQueue($selectedQueue); // Liberar la cola
            })
            ->dispatch();

        // 1. Iniciar registro en BD usando ProcessBatchService
        $processBatch = ProcessBatchService::initProcess( // <-- Capturar el objeto ProcessBatch
            $batch->id,
            $company_id,
            $user_id,
            $totalRows,
            [
                'total_rows' => $totalRows,
                'file_name' => $originalFileName,
                'file_size' => $fileSize,
                'started_at' => now()->toDateTimeString(),
                'completed_at' => null,
                'total_sheets' => 1, // Asumiendo una sola hoja
                'current_sheet' => 1, // Asumiendo una sola hoja
            ]
        );
        // Emitir evento de progreso inicial
        event(new ImportProgressEvent(
            $batch->id, // Usar el batch ID real de Laravel
            0, // processedRecords
            'Archivo recibido y en cola', // currentAction para el evento inicial
            0, // errorCount
            $processBatch->status, // backendStatus
            0, // currentElement
        ));

        return response()->json([
            'batch_id' => $batch->id,
            'message' => 'Proceso iniciado',
            'status' => 'success',
            'code' => '200',
        ]);
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
