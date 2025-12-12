<?php

namespace App\Http\Controllers;

use App\Enums\Filing\StatusFilingInvoiceEnum;
use App\Events\FilingInvoiceRowUpdated;
use App\Exports\Filing\FilingInvoiceExcelErrorsValidationExport;
use App\Helpers\Constants;
use App\Http\Resources\FilingInvoice\FilingInvoicePaginateResource;
use App\Http\Resources\FilingInvoice\FilingInvoiceThirdsPaginateResource;
use App\Repositories\CompanyRepository;
use App\Repositories\FilingInvoiceRepository;
use App\Repositories\FilingRepository;
use App\Repositories\SupportTypeRepository;
use App\Repositories\UserRepository;
use App\Traits\HttpResponseTrait;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class FilingInvoiceController extends Controller
{
    use HttpResponseTrait;

    public function __construct(
        protected UserRepository $userRepository,
        protected FilingRepository $filingRepository,
        protected FilingInvoiceRepository $filingInvoiceRepository,
        protected SupportTypeRepository $supportTypeRepository,
        protected CompanyRepository $companyRepository,
    ) {
    }

    // Nuevo método para paginación
    public function getPaginatedUsers(Request $request, $invoiceId)
    {
        return $this->execute(function () use ($request, $invoiceId) {
            $filingInvoice = $this->filingInvoiceRepository->find($invoiceId, select: ['id', 'invoice_number']);

            $getPaginatedDataRedis = getPaginatedDataRedis($request, $invoiceId, $this->filingInvoiceRepository);

            return [
                'filingInvoice' => $filingInvoice,
                'dataUsers' => $getPaginatedDataRedis['data'],
                'pagination' => $getPaginatedDataRedis['pagination'],
            ];
        });
    }

    public function paginate(Request $request)
    {
        return $this->execute(function () use ($request) {

            $data = $this->filingInvoiceRepository->paginate($request->all());
            $tableData = FilingInvoicePaginateResource::collection($data);

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

    public function countAllDataFiling(Request $request)
    {
        return $this->execute(function () use ($request) {

            $filter = $request->all();

            $filing = $this->filingRepository->find($request->input('filing_id'));

            $data = [
                [
                    'icon' => 'tabler-checkup-list',
                    'color' => 'success',
                    'title' => 'Facturas Pre-radicadas',
                    'value' => $this->filingInvoiceRepository->countData([
                        ...$filter,
                        'status' => StatusFilingInvoiceEnum::FILINGINVOICE_EST_001,
                    ]),
                    'isHover' => false,
                    'to' => null,
                ],
                [
                    'icon' => 'tabler-checkup-list',
                    'color' => 'success',
                    'title' => 'Facturas Radicadas',
                    'value' => $this->filingInvoiceRepository->countData([
                        ...$filter,
                        'status' => StatusFilingInvoiceEnum::FILINGINVOICE_EST_002,
                    ]),
                    'isHover' => false,
                    'to' => null,
                ],
                [
                    'icon' => 'tabler-checkup-list',
                    'color' => 'success',
                    'title' => 'Valor facturas',
                    'value' => formatNumber($filing->sumVr),
                    'isHover' => false,
                    'to' => null,
                ],
                [
                    'icon' => 'tabler-checkup-list',
                    'color' => 'success',
                    'title' => 'Cantidad XML',
                    'value' => $filing->xml_count_validate,
                    'isHover' => false,
                    'to' => null,
                ],
            ];

            return [
                'code' => 200,
                'data' => $data,

            ];
        });
    }

    public function uploadXml(Request $request)
    {
        return $this->execute(function () use ($request) {
            if ($request->hasFile('archiveXml')) {
                // Inicializar variables
                $company_id = $request->input('company_id');
                $third_nit = $request->input('third_nit');
                $filing_invoice = $this->filingInvoiceRepository->find($request->input('filing_invoice_id'));
                $jsonContents = openFileJson($filing_invoice->path_json);
                $file = $request->file('archiveXml');

                $data = [
                    'numInvoice' => $filing_invoice->invoice_number,
                    'file_name' => $file->getClientOriginalName(),
                    'jsonContents' => $jsonContents,
                ];

                // Validar datos del XML
                $infoValidation = validateDataFilesXml($request->file('archiveXml')->path(), $data);

                // logMessage($infoValidation);

                // Determinar el estado y la ruta del archivo XML
                if ($infoValidation['totalErrorMessages'] == 0) {
                    $finalName = "{$third_nit}_{$filing_invoice->invoice_number}_FILEXML.xml";
                    $finalPath = "companies/company_{$company_id}/filings/{$filing_invoice->filing->type->value}/filing_{$filing_invoice->filing->id}/invoices/{$filing_invoice->invoice_number}/xml";

                    $path = $file->storeAs($finalPath, $finalName, Constants::DISK_FILES);
                    $filing_invoice->path_xml = $path;
                    $filing_invoice->status_xml = StatusFilingInvoiceEnum::FILINGINVOICE_EST_003;
                    $filing_invoice->validationXml = null;
                } else {
                    $filing_invoice->status_xml = StatusFilingInvoiceEnum::FILINGINVOICE_EST_005;
                    $filing_invoice->validationXml = json_encode($infoValidation['errorMessages']);
                }

                // Guardar el estado de la factura y validar el estado del filing
                $filing_invoice->save();

                FilingInvoiceRowUpdated::dispatch($filing_invoice->id);

                // Devolver la respuesta adecuada
                return [
                    'code' => 200,
                    'message' => $infoValidation['totalErrorMessages'] == 0 ? 'Archivo subido con éxito' : 'Validaciones finalizadas',
                    'data' => ['validationXml' => json_encode($infoValidation)],
                ];
            }
        });
    }

    public function filingInvoicesId($filingInvoicesId)
    {
        return $this->execute(function () use ($filingInvoicesId) {
            $filingInvoice = $this->filingInvoiceRepository->find($filingInvoicesId);

            return [
                'code' => 200,
                'data' => $filingInvoice,
            ];
        });
    }

    public function showErrorsValidation($filingInvoicesId)
    {
        return $this->execute(function () use ($filingInvoicesId) {

            // Obtener los mensajes de errores de las validaciones
            $errorMessages = $this->filingInvoiceRepository->getValidationsErrorMessages($filingInvoicesId);

            return [
                'code' => 200,
                'errorMessages' => $errorMessages,
            ];
        });
    }

    public function excelErrorsValidation(Request $request)
    {
        return $this->execute(function () use ($request) {

            // Obtener los mensajes de errores de las validaciones
            $data = $this->filingInvoiceRepository->getValidationsErrorMessages($request->input('id'));

            $excel = Excel::raw(new FilingInvoiceExcelErrorsValidationExport($data), \Maatwebsite\Excel\Excel::XLSX);

            $excelBase64 = base64_encode($excel);

            return [
                'code' => 200,
                'excel' => $excelBase64,
            ];
        });
    }

    public function delete($id)
    {
        return $this->runTransaction(function () use ($id) {

            $data = $this->filingInvoiceRepository->find($id);

            if ($data) {
                $data->delete();
            }

            return [
                'code' => 200,
                'message' => 'Registro eliminado con éxito.',
            ];
        });
    }

    public function paginateThirds(Request $request)
    {
        return $this->execute(function () use ($request) {

            $data = $this->filingInvoiceRepository->paginateThirds($request->all());
            $tableData = FilingInvoiceThirdsPaginateResource::collection($data);

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
}