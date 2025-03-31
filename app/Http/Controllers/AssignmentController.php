<?php

namespace App\Http\Controllers;

use App\Enums\Assignment\StatusAssignmentEnum;
use App\Http\Requests\Assignment\AssignmentUploadCsvRequest;
use App\Http\Resources\Assignment\AssignmentPaginateInvoiceAuditResource;
use App\Http\Resources\Assignment\AssignmentPaginatePatientResource;
use App\Http\Resources\Assignment\AssignmentPaginateThirdsResource;
use App\Imports\AssingmentImport;
use App\Repositories\AssignmentBatcheRepository;
use App\Repositories\CompanyRepository;
use App\Repositories\AssignmentRepository;
use App\Repositories\ThirdRepository;
use App\Traits\HttpResponseTrait;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\CacheService;
use App\Helpers\Constants;


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

            $user_id = $request->input('user_id');
            $company_id = $request->input('company_id');

            $csv = Excel::import(new AssingmentImport($user_id, $company_id), $request->file('archiveCsv'));

            return [
                'request' => $request->all(),
                'csv' => $csv,
            ];
        });
    }

    public function AssignmentCount(Request $request)
    {
        return $this->execute(function () use ($request) {

            $cacheKey = $this->cacheService->generateKey("assignments_paginate_count_all_data", $request->all(), 'string');

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
                'countNumberProviders' => formatNumber($countNumberProviders,"",0),
                'outstandingInvoices' => formatNumber($outstandingInvoices,"",0),
                'finalizedInvoices' => formatNumber($finalizedInvoices,"",0),
                'allInvoices' => formatNumber($allInvoices,"",0),
                'percentageProgress' => formatNumber($percentageProgress,""),
            ];
        }, Constants::REDIS_TTL);
        });
    }
}
