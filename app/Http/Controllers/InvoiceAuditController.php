<?php

namespace App\Http\Controllers;

use App\Exports\InvoiceAudit\InvoiceAuditExcelExport;
use App\Exports\Service\ServiceListExcelExport;
use App\Http\Resources\InvoiceAudit\InvoiceAuditListResource;
use App\Http\Resources\InvoiceAudit\InvoiceAuditPaginateBatcheResource;
use App\Http\Resources\InvoiceAudit\InvoiceAuditPaginateInvoiceAuditResource;
use App\Http\Resources\InvoiceAudit\InvoiceAuditPaginatePatientResource;
use App\Http\Resources\InvoiceAudit\InvoiceAuditPaginateServiceResource;
use App\Http\Resources\InvoiceAudit\InvoiceAuditPaginateThirdsResource;
use App\Models\Service;
use App\Repositories\CodeGlosaRepository;
use App\Repositories\InvoiceAuditRepository;
use App\Repositories\PatientRepository;
use App\Repositories\ThirdRepository;
use App\Traits\HttpResponseTrait;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class InvoiceAuditController extends Controller
{
    use HttpResponseTrait;

    public function __construct(
        protected InvoiceAuditRepository $invoiceAuditRepository,
        protected ThirdRepository $thirdRepository,
        protected PatientRepository $patientRepository,
        protected CodeGlosaRepository $codeGlosaRepository,
    ) {}

    public function list(Request $request)
    {
        return $this->execute(function () use ($request) {

            $invoiceAudit = $this->invoiceAuditRepository->list($request->all());
            $tableData = InvoiceAuditListResource::collection($invoiceAudit);

            return [
                'code' => 200,
                'tableData' => $tableData,
                'lastPage' => $invoiceAudit->lastPage(),
                'totalData' => $invoiceAudit->total(),
                'totalPage' => $invoiceAudit->perPage(),
                'currentPage' => $invoiceAudit->currentPage(),
            ];
        });
    }

    public function paginateBatche(Request $request)
    {
        return $this->execute(function () use ($request) {

            $data = $this->invoiceAuditRepository->paginateBatche($request->all());
            $tableData = InvoiceAuditPaginateBatcheResource::collection($data);

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

    public function paginateThirds(Request $request, $assignment_batche_id)
    {
        return $this->execute(function () use ($request, $assignment_batche_id) {

            $request['assignment_batche_id'] = $assignment_batche_id;

            $data = $this->invoiceAuditRepository->paginateThirds($request->all());
            $tableData = InvoiceAuditPaginateThirdsResource::collection($data);

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

            $data = $this->invoiceAuditRepository->paginateInvoiceAudit($request->all());
            $tableData = InvoiceAuditPaginateInvoiceAuditResource::collection($data);

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

            $data = $this->invoiceAuditRepository->paginatePatient($request->all());
            $tableData = InvoiceAuditPaginatePatientResource::collection($data);

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

    public function getInformationSheet(Request $request, $third_id, $invoice_audit_id, $patient_id)
    {
        return $this->execute(function () use ($request, $third_id, $invoice_audit_id, $patient_id) {

            $invoice_audit = $this->invoiceAuditRepository->find($invoice_audit_id);
            $third = $this->thirdRepository->find($third_id);

            $patient = $this->patientRepository->find($patient_id);
            $patient = new InvoiceAuditPaginatePatientResource($patient);


            $value_glosa = $invoice_audit->services->sum("value_glosa");
            $value_approved = $invoice_audit->services->sum("value_approved");

            return [
                'code' => 200,
                'data' => [
                    'invoice_audit' => $invoice_audit,
                    'third' => $third,
                    'patient' => $patient,
                    'value_glosa' => $value_glosa,
                    'value_approved' => $value_approved,
                ],
            ];
        });
    }

    public function getServices(Request $request, $invoice_audit_id, $patient_id)
    {
        return $this->execute(function () use ($request, $invoice_audit_id, $patient_id) {

            $request['invoice_audit_id'] = $invoice_audit_id;

            $request['patient_id'] = $patient_id;

            $data = $this->invoiceAuditRepository->paginateServices($request->all());
            $tableData = InvoiceAuditPaginateServiceResource::collection($data);

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

    public function exportServices(Request $request)
    {
        return $this->execute(function () use ($request) {

            $services = Service::query();
            $services->where(function ($query) use ($request) {

                if (!empty($request['invoice_audit_id'])) {
                    $query->where('invoice_audit_id', $request['invoice_audit_id']);
                }
                if (!empty($request['patient_id'])) {
                    $query->where('patient_id', $request['patient_id']);
                }
                if (!empty($request['third_id'])) {
                    $query->where('third_id', $request['third_id']);
                }
                if (!empty($request['assignment_batch_id'])) {
                    $query->where('assignment_batch_id', $request['assignment_batch_id']);
                }
            });
            $services =  $services->get();

            $glosses = $this->codeGlosaRepository->list(
                [
                    'typeData' => 'all',
                    'is_active' => 1,
                ]
            );
            $attachedData = [
                [
                    "id" => auth()->user()->id,
                ]
            ];

            $excel = Excel::raw(new InvoiceAuditExcelExport($services, $glosses, $attachedData), \Maatwebsite\Excel\Excel::XLSX);

            $excelBase64 = base64_encode($excel);

            return [
                'code' => 200,
                'excel' => $excelBase64,
            ];
        });
    }

    public function exportListServicesExcel(Request $request)
    {
        return $this->execute(function () use ($request) {
            $services = $this->invoiceAuditRepository->paginateServices($request->all());

            $excel = Excel::raw(new ServiceListExcelExport($services), \Maatwebsite\Excel\Excel::XLSX);

            $excelBase64 = base64_encode($excel);

            return [
                'code' => 200,
                'excel' => $excelBase64,
            ];
        });
    }

    public function exportPatients(Request $request)
    {
        return $this->execute(function () use ($request) {

            $services = Service::query()->with([
                "patient" => function ($query) use ($request){
                    if (!empty($request['patient_id'])) {
                        $query->where('id', $request['patient_id']);
                    }
                },
                "invoice_audit" => function ($query) use ($request){
                    if (!empty($request['invoice_audit_id'])) {
                        $query->where('id', $request['invoice_audit_id']);
                    }


                    if (!empty($request['third_id'])) {
                        $query->whereHas('third', function ($subQuery) use ($request) {
                            if (!empty($request['third_id'])) {
                                $subQuery->where('id', $request['third_id']);
                            }
                        });
                    }

                    $query->whereHas('assignment.assignmentBatche', function ($subQuery) use ($request) {
                        if (!empty($request['assignment_batch_id'])) {
                            $subQuery->where('assignment_batch_id', $request['assignment_batch_id']);
                        }
                    });

                },
                // "invoice_audit.assignment.assignmentBatche"
            ]);
            $services->where(function ($query) use ($request) {

                if (!empty($request['invoice_audit_id'])) {
                    $query->where('invoice_audit_id', $request['invoice_audit_id']);
                }
                if (!empty($request['patient_id'])) {
                    $query->where('patient_id', $request['patient_id']);
                }
                if (!empty($request['third_id'])) {

                    $query->whereHas('invoice_audit', function ($subQuery) use ($request) {
                        if (!empty($request['third_id'])) {
                            $subQuery->where('third_id', $request['third_id']);
                        }
                    });
                }
                if (!empty($request['assignment_batch_id'])) {

                    $query->whereHas('invoice_audit.assignment.assignmentBatche', function ($subQuery) use ($request) {
                        if (!empty($request['assignment_batch_id'])) {
                            $subQuery->where('assignment_batch_id', $request['assignment_batch_id']);
                        }
                    });
                }
            });
            $services =  $services->get();


            $glosses = $this->codeGlosaRepository->list(
                [
                    'typeData' => 'all',
                    'is_active' => 1,
                ]
            );
            $attachedData = [
                [
                    "id" => auth()->user()->id,
                ]
            ];

            $excel = Excel::raw(new InvoiceAuditExcelExport($services, $glosses, $attachedData,$request->all()), \Maatwebsite\Excel\Excel::XLSX);

            $excelBase64 = base64_encode($excel);

            return [
                'code' => 200,
                'excel' => $excelBase64,
            ];
        });
    }
}
