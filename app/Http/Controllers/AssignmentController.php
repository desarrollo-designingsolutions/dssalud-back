<?php

namespace App\Http\Controllers;

use App\Helpers\Constants;
use App\Http\Requests\Assignment\AssignmentUploadCsvRequest;
use App\Http\Requests\Assignment\AssignmentStoreRequest;
use App\Http\Resources\Assignment\AssignmentFormResource;
use App\Http\Resources\Assignment\AssignmentPaginateInvoiceAuditResource;
use App\Http\Resources\Assignment\AssignmentPaginatePatientResource;
use App\Http\Resources\Assignment\AssignmentPaginateThirdsResource;
use App\Imports\AssingmentImport;
use App\Models\Assignment;
use App\Models\InvoiceAudit;
use App\Models\Third;
use App\Repositories\CompanyRepository;
use App\Repositories\RoleRepository;
use App\Repositories\AssignmentRepository;
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
    ) {}

    public function paginateThirds(Request $request, $assignment_batche_id)
    {
        return $this->execute(function () use ($request, $assignment_batche_id) {

            $request['assignment_batche_id'] = $assignment_batche_id;

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

            $request['assignment_batche_id'] = $assignment_batche_id;

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

            $user_id = $request->input('user_id');
            $company_id = $request->input('company_id');

            $csv = Excel::import(new AssingmentImport($user_id, $company_id), $request->file('archiveCsv'));

            return [
                'request' => $request->all(),
                'csv' => $csv,
            ];
        });
    }

    public function uploadCsvGlosa(AssignmentUploadCsvRequest $request)
    {
        return $this->runTransaction(function () use ($request) {

            $user_id = $request->input('user_id');
            $company_id = $request->input('company_id');

            $csv = Excel::import(new AssingmentImport($user_id, $company_id), $request->file('archiveCsv'));

            return [
                'request' => $request->all(),
                'csv' => $csv,
            ];
        });
    }
}
