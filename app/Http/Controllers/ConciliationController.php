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

            $conciliationReport = $this->conciliationReportRepository->store($request->all());
            $reconciliationGroup = $this->reconciliationGroupRepository->find(
                id: $request->input("reconciliation_group_id"),
                with: ["invoices:id"],
                select: ["id", "third_id"]
            );

            $invoices_ids = $reconciliationGroup->invoices->pluck("id");
            $invoices = AuditoryFinalReport::whereIn("factura_id", $invoices_ids)->get()
                ->map(function ($value) {
                    return [
                        "iddd" => $value->invoiceAudit?->id,
                        "invoice_number" => $value->invoiceAudit?->invoice_number,
                        "sub_invoice_number" => $value->invoiceAudit?->invoice_number,
                        "gloss_code" =>  $value->codigos_glosa,
                        "contract_number" => $value->contrato,
                        "total_value" => formatNumber($value->invoiceAudit?->total_value),
                        "invoiced_month" => $value->invoiceAudit?->date_entry,
                        "affiliated_department" => $value->invoiceAudit?->third?->departmentAndCity?->departamento,
                        "initial_gloss_value" => formatNumber($value->valor_glosa),
                        "pending_value" => "0",
                        "accepted_value_eps" => formatNumber($value->conciliationResult?->accepted_value_eps),
                        "accepted_value_ips" => formatNumber($value->conciliationResult?->accepted_value_ips),
                        "ratified_value" => formatNumber($value->conciliationResult?->eps_ratified_value),
                        "justification" => "viene de la observacion de la tabla conciliation result",

                    ];
                });
            $third = $reconciliationGroup->third;
            // Concatenar las modalidades separadas por comas
            $modalities = $third->invoiceAudits->pluck('modality')->unique()->implode(',');

            $total_value = 0;
            $initial_gloss_value = 0;
            $accepted_value_eps = 0;
            $accepted_value_ips = 0;
            $ratified_value = 0;

            $invoices = $this->reconciliationGroupInvoiceRepository->getConciliationInvoicesChunk($request)
                ->map(function ($value) use (&$total_value, &$initial_gloss_value, &$accepted_value_eps, &$accepted_value_ips, &$ratified_value) {
                    $total_value += $value->invoiceAudit?->total_value ?? 0;
                    $initial_gloss_value += $value->invoiceAudit?->auditoryFinalReport?->valor_glosa ?? 0;
                    $accepted_value_eps += $value->accepted_value_eps ?? 0;
                    $accepted_value_ips += $value->accepted_value_ips ?? 0;
                    $ratified_value += $value->eps_ratified_value ?? 0;

                    return [
                        "invoice_number" => $value->invoiceAudit?->invoice_number,
                        "sub_invoice_number" => $value->invoiceAudit?->invoice_number,
                        "gloss_code" => "?????",
                        "contract_number" => $value->invoiceAudit?->contract_number,
                        "total_value" => formatNumber($value->invoiceAudit?->total_value),
                        "invoiced_month" => $value->invoiceAudit?->date_entry,
                        "affiliated_department" => $value->invoiceAudit?->third?->departmentAndCity?->departamento,
                        "initial_gloss_value" => formatNumber($value->invoiceAudit?->auditoryFinalReport?->valor_glosa),
                        "pending_value" => "0",
                        "accepted_value_eps" => formatNumber($value->accepted_value_eps),
                        "accepted_value_ips" => formatNumber($value->accepted_value_ips),
                        "ratified_value" => formatNumber($value->eps_ratified_value),
                        "justification" => "viene de la observacion de la tabla conciliation result",
                    ];
                });


            // Formatear la fecha actual en español
            $currentDate = Carbon::now();
            $currentDate->setLocale('es');
            $day = str_pad($currentDate->day, 2, '0', STR_PAD_LEFT); // Ensure two digits for day
            $month = $currentDate->monthName;
            $year = $currentDate->year;
            $formattedDateReport = "$day del mes de $month de $year";




            $data = [
                'modalities' => $modalities,
                'third' => [
                    'name'       => $third->name,
                    'nit'          => $third->nit,
                    'departament' => $third->departmentAndCity?->departamento,
                    'city'    => $third->departmentAndCity?->municipio,
                ],
                'dateConciliation' => $conciliationReport->dateConciliation,
                'formattedDateReport' => $formattedDateReport,
                'totales' => [
                    'total_value' => formatNumber($total_value),
                    'initial_gloss_value' => formatNumber($initial_gloss_value),
                    'pending_value' => formatNumber(0),
                    'accepted_value_eps' => formatNumber($accepted_value_eps),
                    'accepted_value_ips' => formatNumber($accepted_value_ips),
                    'ratified_value' => formatNumber($ratified_value),
                ],
                'signatures' => [
                    'nameIPSrepresentative' => $conciliationReport->nameIPSrepresentative,
                    'positionIPSrepresentative' => $conciliationReport->positionIPSrepresentative,
                    'elaborator_full_name' => $conciliationReport->elaborator?->full_name,
                    'elaborator_position' => $conciliationReport->elaborator_position,
                    'reviewer_full_name' => $conciliationReport->reviewer?->full_name,
                    'reviewer_position' => $conciliationReport->reviewer_position,
                    'approver_full_name' => $conciliationReport->approver?->full_name,
                    'approver_position' => $conciliationReport->approver_position,
                    'legal_representative_full_name' => $conciliationReport->legal_representative?->full_name,
                    'legal_representative_position' => $conciliationReport->legal_representative_position,
                    'health_audit_director_full_name' => $conciliationReport->health_audit_director?->full_name,
                    'health_audit_director_position' => $conciliationReport->health_audit_director_position,
                    'vp_planning_control_full_name' => $conciliationReport->vp_planning_control?->full_name,
                    'vp_planning_control_position' => $conciliationReport->vp_planning_control_position,
                ],
                'invoices' => $invoices,
            ];

            $excel = Excel::raw(new ConciliationGenerateConciliationReportExcelExport($data), \Maatwebsite\Excel\Excel::XLSX);

            $excelBase64 = base64_encode($excel);


            return [
                'code' => 200,
                'message' => "Registro actualizado con éxito.",
                'excel' => $excelBase64,
            ];
        });
    }
}
