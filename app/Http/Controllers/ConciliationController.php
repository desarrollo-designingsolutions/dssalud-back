<?php

namespace App\Http\Controllers;

use App\Events\ImportProgressEvent;
use App\Exports\Conciliation\ConciliationExcelExport;
use App\Exports\Conciliation\ConciliationGenerateConciliationReportExcelExport;
use App\Helpers\Constants;
use App\Http\Requests\Conciliation\ConciliationChangeStatusSaveRequest;
use App\Http\Requests\Conciliation\ConciliationGenerateConciliationReportSaveRequest;
use App\Http\Requests\Conciliation\ConciliationUploadFileRequest;
use App\Http\Resources\Conciliation\ConciliationGenerateConciliationReportFormResource;
use App\Http\Resources\Conciliation\ConciliationInvoicePaginateResource;
use App\Http\Resources\Conciliation\ConciliationPaginateResource;
use App\Http\Resources\Conciliation\ConciliationShowResource;
use App\Imports\ConciliationImport\Jobs\ProcessCsvImportJob;
use App\Jobs\Conciliation\CreateConciliationReport;
use App\Jobs\CreateConciliationExport;
use App\Models\AuditoryFinalReport;
use App\Models\InvoiceAudit;
use App\Repositories\ConciliationChangeStatusRepository;
use App\Repositories\ConciliationReportRepository;
use App\Repositories\ReconciliationGroupInvoiceRepository;
use App\Repositories\ReconciliationGroupRepository;
use App\Services\CacheService;
use App\Services\Conciliation\ExcelStructureValidator;
use App\Services\ProcessBatchService;
use App\Traits\HttpResponseTrait;
use Carbon\Carbon;
use Illuminate\Bus\Batch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        protected QueryController $queryController,
        protected ConciliationChangeStatusRepository $conciliationChangeStatusRepository,
        protected ConciliationReportRepository $conciliationReportRepository,
        protected CacheService $cacheService,
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
        $reconciliation_group_id = $request->input('reconciliation_group_id');
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
        $fileName = $fileName . '_' . time() . '.csv';
        $filePath = $uploadedFile->storeAs('temp', $fileName, Constants::DISK_FILES);
        $fullPath = storage_path('app/public/' . $filePath);

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
            ProcessCsvImportJob::dispatch($fullPath, $batchId, $totalRows,$reconciliation_group_id)->onQueue('import_conciliations');

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
            Log::error("Error en uploadFile para batch ID {$batchId}: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Limpiar metadatos de Redis si el proceso falla antes de despachar el Job
            Redis::connection('redis_6380')->del("batch:{$batchId}:metadata");

            return response()->json([
                'message' => 'Error interno al procesar el archivo: ' . $e->getMessage(),
                'status' => 'error',
                'code' => '500',
            ], 500);
        }
    }

    public function excelExportConciliationInvoices(Request $request)
    {
        return $this->execute(function () use ($request) {
            $fileName = 'conciliation_invoices_' . now()->format('Ymd_His') . '.xlsx';

            // Disparamos el job principal
            CreateConciliationExport::dispatch(
                $request->all(),
                $request->input("user_id"),
                $fileName
            )->onqueue('download_files');

            return [
                'code' => 200, // Accepted
                'message' => 'La exportación está siendo procesada. Recibirás una notificación cuando esté lista para descargar.',
                'download_url' => null // O podrías devolver una URL para verificar el estado
            ];
        });
    }


    public function changeStatusForm(Request $request)
    {
        return $this->execute(function () use ($request) {

            $statusReconciliationGroupEnum = $this->queryController->selectStatusReconciliationGroupEnum(request());

            return [
                'code' => 200,
                "statusReconciliationGroupEnum" => $statusReconciliationGroupEnum["statusReconciliationGroupEnum_arrayInfo"]
            ];
        });
    }

    public function changeStatusSave(ConciliationChangeStatusSaveRequest $request)
    {
        return $this->execute(function () use ($request) {

            $this->conciliationChangeStatusRepository->store($request->all());

            $this->reconciliationGroupRepository->store([
                "id" => $request->input("reconciliation_group_id"),
                "status" => $request->input("status"),
            ]);


            return [
                'code' => 200,
                'message' => "Registro actualizado con éxito.",
            ];
        });
    }

    public function generateConciliationReportForm(Request $request)
    {
        return $this->execute(function () use ($request) {

            $users = $this->queryController->selectInfiniteUser(request());

            $form = null;
            $conciliationReport = $this->conciliationReportRepository->searchOne([
                "reconciliation_group_id" => $request->input("reconciliation_group_id")
            ]);
            if ($conciliationReport) {
                $form = new ConciliationGenerateConciliationReportFormResource($conciliationReport);
            }

            return [
                'code' => 200,
                'form' => $form,
                ...$users,
            ];
        });
    }

    public function generateConciliationReportSave(ConciliationGenerateConciliationReportSaveRequest $request)
    {
        return $this->execute(function () use ($request) {
            // Guardar el reporte de conciliación
            $conciliationReport = $this->conciliationReportRepository->store($request->except(["user_id"]));

            // Generar nombre único para el archivo
            $fileName = 'conciliation_report_' . now()->format('Ymd_His') . '.xlsx';

            // Disparamos el job principal
            CreateConciliationReport::dispatch(
                $request->all(),
                $request->input("user_id"),
                $fileName,
                $request->input("reconciliation_group_id")
            )->onqueue('download_files');

            return [
                'code' => 200,
                'message' => "El reporte de conciliación se está generando en segundo plano. Se le notificará cuando esté listo.",
                'file_name' => $fileName
            ];
        });
    }
}
