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
use App\Imports\ConciliationImport\Jobs\ProcessCsvImportJob;
use App\Repositories\ReconciliationGroupInvoiceRepository;
use App\Repositories\ReconciliationGroupRepository;
use App\Services\Conciliation\ExcelStructureValidator;
use App\Services\ProcessBatchService;
use App\Traits\HttpResponseTrait;
use Illuminate\Bus\Batch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
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
        $batchId = (string) Str::uuid();

        // Obtener el nombre original del archivo con extensión
        $fileNameWithExtension = strtolower($uploadedFile->getClientOriginalName());
        // Obtener el nombre del archivo sin la extensión
        $fileName = pathinfo($fileNameWithExtension, PATHINFO_FILENAME);

        $fileExtension = strtolower($uploadedFile->getClientOriginalExtension());

        // Validar que el archivo sea CSV
        if ($fileExtension !== 'csv') {
            return response()->json([
                'message' => 'Solo se permiten archivos en formato CSV.',
                'status' => 'error',
                'code' => '400',
            ], 400);
        }

        // Guardar archivo CSV
        $fileName = $fileName.'_'.time().'.csv';
        $filePath = $uploadedFile->storeAs('temp', $fileName, Constants::DISK_FILES);
        $fullPath = storage_path('app/public/'.$filePath);

        if (! file_exists($fullPath)) {
            Log::error("Error al guardar el archivo CSV: {$fullPath}");

            return response()->json([
                'message' => 'Error al guardar el archivo.',
                'status' => 'error',
                'code' => '500',
            ], 500);
        }

        try {
            // Procesar el CSV directamente
            $totalRows = 0;
            $csvFile = fopen($fullPath, 'r');

            // Leer y validar encabezados
            $headers = fgetcsv($csvFile, 0, ';');
            if ($headers === false || empty($headers)) {
                throw new \Exception('El archivo CSV está vacío o no tiene encabezados válidos.');
            }

            // Procesar filas
            while (($row = fgetcsv($csvFile, 0, ';')) !== false) {
                $totalRows++;
            }

            fclose($csvFile);

            // Log::info("Archivo CSV procesado exitosamente. Total de filas: {$totalRows}");

            // Opcional: Eliminar el archivo Excel original si ya no se necesita
            // \Illuminate\Support\Facades\Storage::disk(Constants::DISK_FILES)->delete($filePath);

            // Almacenar metadatos iniciales del batch en Redis
            $metadata = [
                'total_rows' => (string) $totalRows,
                'file_name' => (string) $fileName,
                'file_size' => (string) filesize($fullPath),
                'started_at' => now()->toDateTimeString(),
                'completed_at' => 'N/A',
                'current_sheet' => (string) 1,
                'total_sheets' => (string) 1,
            ];
            Redis::connection('redis_6380')->hmset("batch:{$batchId}:metadata", $metadata);
            Redis::connection('redis_6380')->expire("batch:{$batchId}:metadata", 3600 * 24);

            // Iniciar registro en BD usando ProcessBatchService
            $processBatch = ProcessBatchService::initProcess(
                $batchId,
                $company_id,
                $user_id,
                $totalRows,
                $metadata
            );

            // Siempre despachar el Job de importación de CSV
            ProcessCsvImportJob::dispatch($fullPath, $batchId, $totalRows)->onQueue('import_conciliations');

            // Despachar el evento inicial de progreso para la UI
            ImportProgressEvent::dispatch(
                $batchId,
                (string) 0,
                'Archivo encolado para procesamiento',
                (string) 0,
                'queued',
                '0'
            );

            // Log::info("Archivo {$fullPath} encolado para procesamiento con Batch ID: {$batchId}");

            return response()->json([
                'batch_id' => $batchId,
                'message' => 'Proceso de importación iniciado y encolado.',
                'status' => 'success',
                'code' => '200',
            ]);
        } catch (Throwable $e) {
            Log::error("Error en uploadFile para batch ID {$batchId}: ".$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Limpiar metadatos de Redis si el proceso falla antes de despachar el Job
            Redis::connection('redis_6380')->del("batch:{$batchId}:metadata");

            return response()->json([
                'message' => 'Error interno al procesar el archivo: '.$e->getMessage(),
                'status' => 'error',
                'code' => '500',
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
