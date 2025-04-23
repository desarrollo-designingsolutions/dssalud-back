<?php

namespace App\Http\Controllers;

use App\Attributes\Description;
use App\Enums\Assignment\StatusAssignmentEnum;
use App\Events\ModalError;
use App\Exports\Assignment\AssignmentExcelErrorsValidationExport;
use App\Exports\Assignment\AssignmentExcelExport;
use App\Helpers\Common\ErrorCollector;
use App\Helpers\Common\ImportCsvValidator;
use App\Helpers\Constants;
use App\Http\Requests\Assignment\AssignmentUploadCsvRequest;
use App\Http\Resources\Assignment\AssignmentPaginateInvoiceAuditResource;
use App\Http\Resources\Assignment\AssignmentPaginatePatientResource;
use App\Http\Resources\Assignment\AssignmentPaginateThirdsResource;
use App\Imports\AssingmentImport;
use App\Jobs\BrevoProcessSendEmail;
use App\Models\User;
use App\Notifications\BellNotification;
use App\Repositories\AssignmentBatcheRepository;
use App\Repositories\AssignmentRepository;
use App\Repositories\CompanyRepository;
use App\Repositories\InvoiceAuditRepository;
use App\Repositories\ThirdRepository;
use App\Repositories\UserRepository;
use App\Services\CacheService;
use App\Traits\HttpResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use ReflectionEnumUnitCase;

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

            $keyErrorRedis = 'list:assignment_import_errors_' . $request->input('user_id');

            $user_id = $request->input('user_id');
            $company_id = $request->input('company_id');

            $assignmentBatches = $this->assignmentBatcheRepository->list([
                "company_id" => $company_id,
                "typeData" => "all",
            ]);

            $users = $this->userRepository->list([
                "is_active" => 1,
                "company_id" => $company_id,
                "typeData" => "all",
            ]);

            $auditUsers = $this->userRepository->getAuditUsers([
                "is_active" => 1,
                "company_id" => $company_id,
                "typeData" => "all",
            ]);

            $invoiceAudits = $this->invoiceAuditRepository->list([
                "company_id" => $company_id,
                "typeData" => "all",
            ]);
            
            $assignmentStatusEnumValues = array_column(StatusAssignmentEnum::cases(), 'value');

            $file = $request->file('archiveCsv');

            $file_path = $file->getRealPath();

            if (!ImportCsvValidator::validate($user_id, $keyErrorRedis, $file_path, 5, 'assignment')) {
                $errors = ErrorCollector::getErrors($keyErrorRedis);  // Obtener lista de errores

                // Convert array to JSON
                $routeJson = null;
                if (count($errors) > 0) {
                    $nameFile = 'error_' . $user_id . '.json';
                    $routeJson = 'companies/company_' . $company_id . '/assignment/errors/' . $nameFile; // Ruta donde se guardará la carpeta
                    Storage::disk(Constants::DISK_FILES)->put($routeJson, json_encode($errors, JSON_PRETTY_PRINT));
                }

                // Enviar notificación al usuario
                $title = 'Importación de asignaciones';
                $subtitle = 'Se encontraron errores en la estructura del archivo que esta intentando importar.';

                $this->sendNotification(
                    $user_id,
                    [
                        'title' => $title,
                        'subtitle' => $subtitle,
                        'data_import' => $errors,
                    ]
                );
                
                // Emitir errores al front
                ModalError::dispatch("assignmentStructureModalErrors.{$user_id}", $routeJson);

                return [
                    'code' => 422
                ];
            } else {
                $csv = Excel::import(new AssingmentImport($user_id, $company_id, $assignmentBatches, $users, $auditUsers, $invoiceAudits, $assignmentStatusEnumValues, $file_path), $request->file('archiveCsv'));

                return [
                    'request' => $request->all(),
                    'csv' => $csv,
                ];
            }
        });
    }

    private function sendNotification($userId, $data)
    {
        // Obtener el objeto User a partir del ID
        $user = User::find($userId);


        if ($user) {
            // Enviar notificación
            $user->notify(new BellNotification($data));

            // Enviar el correo usando el job de Brevo
            BrevoProcessSendEmail::dispatch(
                emailTo: [
                    [
                        "name" => $user->full_name,
                        "email" => $user->email,
                    ]
                ],
                subject: $data['title'],
                templateId: 11,  // El ID de la plantilla de Brevo que quieres usar
                params: [
                    "full_name" => $user->full_name,
                    "subtitle" => $data['subtitle'],
                    "bussines_name" => $user->company?->name,
                    "data_import" => $data['data_import'],
                    "show_table_errors" => count($data['data_import']) > 0 ? true : false,
                ],
            );
        }
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
                    'status_iqual_to' => [StatusAssignmentEnum::ASSIGNMENT_EST_001, StatusAssignmentEnum::ASSIGNMENT_EST_002],
                    'assignment_batch_id' => $request['assignment_batch_id'],
                    'third_id' => $request['third_id'],
                ]);

                $finalizedInvoices = $this->assignmentRepository->countNumberProviders([
                    'status_iqual_to' => [StatusAssignmentEnum::ASSIGNMENT_EST_003],
                    'assignment_batch_id' => $request['assignment_batch_id'],
                    'third_id' => $request['third_id'],
                ]);

                $allInvoices = $this->assignmentRepository->countNumberProviders([
                    'assignment_batch_id' => $request['assignment_batch_id'],
                    'third_id' => $request['third_id'],
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

            $jsonContent = openFileJson($request["url_json"]);

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
                "company_id" => $company_id,
                "typeData" => "all",
            ]);

            $users = $this->userRepository->getAuditUsers([
                "is_active" => 1,
                "company_id" => $company_id,
                "typeData" => "all",
            ]);

            $invoiceAudits = $this->invoiceAuditRepository->list([
                "company_id" => $company_id,
                "typeData" => "all",
            ]);

            $assignmentStatusEnumValues = array_map(function ($case) {
                return [
                    'value' => $case->value,
                    'description' => $case->description(),
                ];
            }, StatusAssignmentEnum::cases());

            $excel = Excel::raw(new AssignmentExcelExport($assignmentBatches, $users, $invoiceAudits, $assignmentStatusEnumValues, $request->all()), \Maatwebsite\Excel\Excel::XLSX);

            $excelBase64 = base64_encode($excel);


            // // Obtener el objeto User a partir del ID
            // $user = $this->userRepository->find($user_id);

            // if ($user) {
            //     // Enviar notificación
            //     // $user->notify(new BellNotification($data));

            //     // Enviar el correo usando el job de Brevo
            //     BrevoProcessSendEmail::dispatch(
            //         emailTo: [
            //             [
            //                 "name" => $user->full_name,
            //                 "email" => $user->email,
            //             ]
            //         ],
            //         subject: "Exportacion de servicios",
            //         templateId: 9,  // El ID de la plantilla de Brevo que quieres usar
            //         params: [
            //             "full_name" => $user->full_name,
            //             "subtitle" => "informacion de los servicios, descargue el archivo donde se muestra la informacion de los servicios",
            //             "bussines_name" => $user->company?->name,
            //         ],
            //         attachments: [
            //             [
            //                 'name' => 'Servicios.xlsx',
            //                 'content' => $excelBase64,
            //             ],
            //         ],
            //     );
            // }


            return [
                'code' => 200,
                'excel' => $excelBase64,
            ];
        });
    }
}
