<?php

namespace App\Http\Controllers;

use App\Enums\Assignment\StatusAssignmentEnum;
use App\Exports\Assignment\AssignmentExcelErrorsValidationExport;
use App\Exports\Assignment\AssignmentExcelExport;
use App\Helpers\Constants;
use App\Http\Requests\Assignment\AssignmentUploadCsvRequest;
use App\Http\Resources\Assignment\AssignmentPaginateInvoiceAuditResource;
use App\Http\Resources\Assignment\AssignmentPaginatePatientResource;
use App\Http\Resources\Assignment\AssignmentPaginateThirdsResource;
use App\Imports\AssingmentImportValidationStructure;
use App\Repositories\AssignmentBatcheRepository;
use App\Repositories\AssignmentRepository;
use App\Repositories\CompanyRepository;
use App\Repositories\InvoiceAuditRepository;
use App\Repositories\ThirdRepository;
use App\Repositories\UserRepository;
use App\Services\CacheService;
use App\Traits\HttpResponseTrait;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class AssignmentController extends Controller
{
    use HttpResponseTrait;

    public function __construct(
        protected QueryController $queryController,
        protected AssignmentRepository $assignmentRepository,
        protected CompanyRepository $companyRepository,
        protected AssignmentBatcheRepository $assignmentBatcheRepository,
        protected ThirdRepository $thirdRepository,
        protected CacheService $cacheService,
        protected UserRepository $userRepository,
        protected InvoiceAuditRepository $invoiceAuditRepository,
    ) {}

    public function paginateThirds(Request $request, $assignment_batch_id)
    {
        return $this->execute(function () use ($request, $assignment_batch_id) {

            $request['assignment_batch_id'] = $assignment_batch_id;

            $data = $this->assignmentRepository->paginateThirds($request->all());
            $tableData = AssignmentPaginateThirdsResource::collection($data);

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

    public function paginateInvoiceAudit(Request $request, $assignment_batche_id, $third_id)
    {
        return $this->execute(function () use ($request, $assignment_batche_id, $third_id) {

            $request['assignment_batch_id'] = $assignment_batche_id;

            $request['third_id'] = $third_id;

            $data = $this->assignmentRepository->paginateInvoiceAudit($request->all());
            $tableData = AssignmentPaginateInvoiceAuditResource::collection($data);

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

    public function paginatePatient(Request $request, $assignment_batche_id, $third_id, $invoice_audit_id)
    {
        return $this->execute(function () use ($request, $assignment_batche_id, $third_id, $invoice_audit_id) {

            $request['assignment_batche_id'] = $assignment_batche_id;

            $request['third_id'] = $third_id;

            $request['invoice_audit_id'] = $invoice_audit_id;

            $data = $this->assignmentRepository->paginatePatient($request->all());
            $tableData = AssignmentPaginatePatientResource::collection($data);

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

    public function uploadCsv(AssignmentUploadCsvRequest $request)
    {
        return $this->runTransaction(function () use ($request) {

            ini_set('memory_limit', '1024M');

            $user_id = $request->input('user_id');
            $company_id = $request->input('company_id');

            $assignmentBatches = $this->assignmentBatcheRepository->list([
                'company_id' => $company_id,
                'typeData' => 'all',
            ]);

            $users = $this->userRepository->list([
                'is_active' => 1,
                'company_id' => $company_id,
                'typeData' => 'all',
            ]);

            $auditUsers = $this->userRepository->getAuditUsers([
                'is_active' => 1,
                'company_id' => $company_id,
                'typeData' => 'all',
            ]);

            $assignmentStatusEnumValues = array_column(StatusAssignmentEnum::cases(), 'value');

            if ($request->hasFile('archiveCsv')) {
                $file = $request->file('archiveCsv');
                $ruta = '/companies/company_'.$company_id.'/assignments/import_temp/'.$user_id; // Ruta donde se guardará la carpeta
                $nombreArchivo = $file->getClientOriginalName(); // Obtiene el nombre original del archivo
                $path_csv = $file->storeAs($ruta, $nombreArchivo, Constants::DISK_FILES); // Guarda el archivo con el nombre original
            }

            $csv = Excel::import(new AssingmentImportValidationStructure($user_id, $company_id, $assignmentBatches, $users, $auditUsers, $assignmentStatusEnumValues, $path_csv), $request->file('archiveCsv'));

            return [
                'request' => $request->all(),
                'csv' => $csv,
            ];
        });
    }

    private function excelErrorsValidationStructure($data)
    {

        $excel = Excel::raw(new AssignmentExcelErrorsValidationExport($data), \Maatwebsite\Excel\Excel::XLSX);

        return $excel;
    }

    private function exportCsvErrorsValidationStructure($data)
    {
        // Agrupar por 'row'
        $groupedErrors = collect($data)->groupBy('row');

        // Obtener un solo 'data' por grupo (el primero, por ejemplo)
        $result = $groupedErrors->map(function ($group) {
            // Tomar el primer elemento del grupo y devolver solo su 'data'
            return $group->first()['data'] ?? null;
        })->values();

        // Generar el CSV con Laravel Excel
        $csv = Excel::raw(new AssignmentExcelErrorsValidationExport($result), \Maatwebsite\Excel\Excel::CSV);

        return $csv;
    }

    public function AssignmentCount(Request $request)
    {
        return $this->execute(function () use ($request) {

            $cacheKey = $this->cacheService->generateKey('assignments_paginate_count_all_data', $request->all(), 'string');

            return $this->cacheService->remember($cacheKey, function () use ($request) {

                $countNumberProviders = $this->thirdRepository->getTotalThirdsInAssignedAudits($request->all());

                $outstandingInvoices = $this->assignmentRepository->countNumberProviders([
                    'status_diff_to' => [StatusAssignmentEnum::ASSIGNMENT_EST_003],
                    'assignment_batch_id' => $request['assignment_batch_id'],
                    'third_id' => $request['third_id'],
                    'company_id' => $request['company_id'],
                    'user_id' => $request['user_id'],
                ]);

                $finalizedInvoices = $this->assignmentRepository->countNumberProviders([
                    'status_iqual_to' => [StatusAssignmentEnum::ASSIGNMENT_EST_003],
                    'assignment_batch_id' => $request['assignment_batch_id'],
                    'third_id' => $request['third_id'],
                    'company_id' => $request['company_id'],
                    'user_id' => $request['user_id'],
                ]);

                $allInvoices = $this->assignmentRepository->countNumberProviders([
                    'assignment_batch_id' => $request['assignment_batch_id'],
                    'third_id' => $request['third_id'],
                    'company_id' => $request['company_id'],
                    'user_id' => $request['user_id'],
                ]);

                $percentageProgress = $allInvoices > 0 ? floor(($finalizedInvoices / $allInvoices) * 100 * 100) / 100 : 0;

                return [
                    'code' => 200,
                    'countNumberProviders' => formatNumber($countNumberProviders, '', 0),
                    'outstandingInvoices' => formatNumber($outstandingInvoices, '', 0),
                    'finalizedInvoices' => formatNumber($finalizedInvoices, '', 0),
                    'allInvoices' => formatNumber($allInvoices, '', 0),
                    'percentageProgress' => formatNumber($percentageProgress, ''),
                ];
            }, Constants::REDIS_TTL);
        });
    }

    public function excelErrorsValidation(Request $request)
    {
        return $this->execute(function () use ($request) {

            $user_id = $request->input('user_id');

            // Obtener los mensajes de errores de las validaciones
            $data = $this->assignmentRepository->getValidationsErrorMessages($user_id);

            // Excluir el campo 'data' de cada elemento
            $filteredData = collect($data)->map(function ($item) {
                return collect($item)->except('data')->toArray();
            });

            $excel = Excel::raw(new AssignmentExcelErrorsValidationExport($filteredData, false, true), \Maatwebsite\Excel\Excel::XLSX);

            $excelBase64 = base64_encode($excel);

            return [
                'code' => 200,
                'excel' => $excelBase64,
            ];
        });
    }

    public function exportCsvErrorsValidation(Request $request)
    {
        return $this->execute(function () use ($request) {

            $user_id = $request->input('user_id');

            // Obtener los mensajes de errores de las validaciones
            $data = $this->assignmentRepository->getValidationsErrorMessages($user_id);

            // Agrupar por 'row'
            $groupedErrors = collect($data)->groupBy('row');

            // Obtener un solo 'data' por grupo (el primero, por ejemplo)
            $result = $groupedErrors->map(function ($group) {
                // Tomar el primer elemento del grupo y devolver solo su 'data'
                return $group->first()['data'] ?? null;
            })->values();

            // Generar el CSV con Laravel Excel
            $csv = Excel::raw(new AssignmentExcelErrorsValidationExport($result, true), \Maatwebsite\Excel\Excel::CSV);

            $excelBase64 = base64_encode($csv);

            return [
                'code' => 200,
                'excel' => $excelBase64,
            ];
        });
    }

    public function getContentJson(Request $request)
    {
        return $this->execute(function () use ($request) {
            // Obtener el contenido del archivo

            $jsonContent = openFileJson($request['url_json']);

            return [
                'code' => 200,
                'data' => $jsonContent,
            ];
        });
    }

    public function exportDataToAssignmentImportCsv(Request $request)
    {
        return $this->execute(function () use ($request) {

            ini_set('memory_limit', '1024M');

            $user_id = $request->input('user_id');
            $company_id = $request->input('company_id');

            $assignmentBatches = $this->assignmentBatcheRepository->list([
                'company_id' => $company_id,
                'typeData' => 'all',
            ]);

            $users = $this->userRepository->getAuditUsers([
                'is_active' => 1,
                'company_id' => $company_id,
                'typeData' => 'all',
            ]);

            $keyData = "invoice_audits:company_{$company_id}:cronjob_";

            $invoiceAudits = getCronjobHashes($keyData);

            $assignmentStatusEnumValues = array_map(function ($case) {
                return [
                    'value' => $case->value,
                    'description' => $case->description(),
                ];
            }, StatusAssignmentEnum::cases());

            $excel = Excel::raw(new AssignmentExcelExport($assignmentBatches, $users, $invoiceAudits, $assignmentStatusEnumValues, $request->all()), \Maatwebsite\Excel\Excel::XLSX);

            $excelBase64 = base64_encode($excel);

            return [
                'code' => 200,
                'excel' => $excelBase64,
            ];
        });
    }
}
