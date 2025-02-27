<?php

namespace App\Http\Controllers;

use App\Enums\Filing\StatusFillingInvoiceEnum;
use App\Events\FilingInvoiceRowUpdated;
use App\Http\Resources\Filing\FilingInvoiceListResource;
use App\Repositories\CompanyRepository;
use App\Repositories\FilingInvoiceRepository;
use App\Repositories\FilingRepository;
use App\Repositories\SupportTypeRepository;
use App\Repositories\UserRepository;
use App\Services\Redis\TemporaryFilingService;
use App\Traits\HttpTrait;
use Illuminate\Http\Request;
use Saloon\XmlWrangler\XmlReader;

class FilingInvoiceController extends Controller
{
    use HttpTrait;

    public function __construct(
        protected UserRepository $userRepository,
        protected FilingRepository $filingRepository,
        protected TemporaryFilingService $tempFilingService,
        protected FilingInvoiceRepository $filingInvoiceRepository,
        protected SupportTypeRepository $supportTypeRepository,
        protected CompanyRepository $companyRepository,
    ) {
    }

    // Nuevo método para paginación
    public function getPaginatedUsers(Request $request, $invoiceId)
    {
        //OPCION 2
        return $this->execute(function () use ($request, $invoiceId) {
            return getPaginatedDataRedis($request, $invoiceId, $this->filingInvoiceRepository);
        });
    }

    public function list(Request $request)
    {
        return $this->execute(function () use ($request) {

            $filings = $this->filingInvoiceRepository->list($request->all());
            $listRips = FilingInvoiceListResource::collection($filings);

            return [
                'code' => 200,
                'tableData' => $listRips,
                'lastPage' => $filings->lastPage(),
                'totalData' => $filings->total(),
                'totalPage' => $filings->perPage(),
                'currentPage' => $filings->currentPage(),
            ];
        });
    }

    public function countAllDataFiling(Request $request)
    {
        return $this->execute(function () use ($request) {

            $filter = $request->all();

            $filing = $this->filingRepository->find($request->input("filing_id"));

            $data = [
                [
                    "icon" => "tabler-checkup-list",
                    "color" => "success",
                    "title" => "Facturas Pre-radicadas",
                    "value" => $this->filingInvoiceRepository->countData([
                        ...$filter,
                        "status" => StatusFillingInvoiceEnum::PRE_FILING
                    ]),
                    "isHover" => false,
                    "to" => null,
                ],
                [
                    "icon" => "tabler-checkup-list",
                    "color" => "success",
                    "title" => "Facturas Radicadas",
                    "value" => $this->filingInvoiceRepository->countData([
                        ...$filter,
                        "status" => StatusFillingInvoiceEnum::FILING
                    ]),
                    "isHover" => false,
                    "to" => null,
                ],
                [
                    "icon" => "tabler-checkup-list",
                    "color" => "success",
                    "title" => "Valor pre-radicado",
                    "value" => formatNumber($filing->sumVr),
                    "isHover" => false,
                    "to" => null,
                ],
                [
                    "icon" => "tabler-checkup-list",
                    "color" => "success",
                    "title" => "Cantidad XML",
                    "value" => $filing->xml_count_validate,
                    "isHover" => false,
                    "to" => null,
                ],
            ];


            return [
                'code' => 200,
                'data' => $data,

            ];
        });
    }

    public function uploadXML(Request $request)
    {
        return $this->execute(function () use ($request) {
            if ($request->hasFile('archiveXml')) {
                // Inicializar variables
                $company_id = $request->input("company_id");
                $company = $this->companyRepository->find($company_id);
                $filing_invoice = $this->filingInvoiceRepository->find($request->input('filing_invoice_id'));
                $jsonContents = openFileJson($filing_invoice->path_json);
                $file = $request->file('archiveXml');

                $data = [
                    'numInvoice' => $filing_invoice->invoice_number,
                    'file_name' => $file->getClientOriginalName(),
                    'jsonContents' => $jsonContents,
                ];

                // Validar datos del XML
                $infoValidation = validateDataFilesXml($request->file('archiveXml'), $data);

                // Determinar el estado y la ruta del archivo XML
                if ($infoValidation['totalErrorMessages'] == 0) {
                    $finalName = "{$company->nit}_{$filing_invoice->invoice_number}_{$file->getClientOriginalName()}";
                    $finalPath = "companies/company_{$company_id}/filings/{$filing_invoice->filing->type->value}/filing_{$filing_invoice->filing->id}/invoices/{$filing_invoice->invoice_number}/supports/{$finalName}";

                    $path = $file->store($finalPath);
                    $filing_invoice->path_xml = $path;
                    $filing_invoice->status_xml = StatusFillingInvoiceEnum::VALIDATED;
                    $filing_invoice->validationXml = null;
                } else {
                    $filing_invoice->status_xml = StatusFillingInvoiceEnum::ERROR_XML;
                    $filing_invoice->validationXml = json_encode($infoValidation);
                }

                // Guardar el estado de la factura y validar el estado del filing
                $filing_invoice->save();
                validateFilingStatus($filing_invoice->filing->id);
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

}
