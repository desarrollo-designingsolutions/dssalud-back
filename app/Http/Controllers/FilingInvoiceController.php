<?php

namespace App\Http\Controllers;

use App\Enums\Filing\StatusFillingInvoiceEnum;
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
    ) {}

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
                    "value" =>  $this->filingInvoiceRepository->countData([
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
            $company_id = $request->input("company_id");
            // $user_id = $request->input("user_id");
            $company = $this->companyRepository->find($company_id);
            $xmlData = [];

            if ($request->hasFile('archiveXml')) {
                // Obtiene el archivo
                $archivoXml = $request->file('archiveXml');

                $contenidoXml = file_get_contents($archivoXml->path());
                $reader = XmlReader::fromString($contenidoXml);
                $xmlData = $reader->values(); // Array of values.
            }

            $filing_invoice = $this->filingInvoiceRepository->find($request->input('filing_invoice_id'));
            $jsonContents = openFileJson($filing_invoice->path_json);

            // $infoValidation = validateDataFilesXml($xmlData, $jsonContents);
            $infoValidation = [
                'errorMessages' => [],
                'totalErrorMessages' => 0,
            ];

            if ($infoValidation['totalErrorMessages'] == 0) {
                // Guarda el JSON en el sistema de archivos usando el disco predeterminado (puede configurar otros discos si es necesario)
                $file = $request->file('archiveXml');

                // Nombre del archivo en el sistema de archivos
                $finalName = "{$company->nit}_{$filing_invoice->invoice_number}_{$file->getClientOriginalName()}";

                // Ruta del archivo en el sistema de archivos
                $finalPath = "companies/company_{$company_id}/filings/{$filing_invoice->filing->type->value}/filing_{$filing_invoice->filing->id}/invoices/{$filing_invoice->invoice_number}/supports/{$finalName}";

                $path = $file->store($finalPath);
                $filing_invoice->path_xml = $path;
                $filing_invoice->status_xml = StatusFillingInvoiceEnum::VALIDATED;
                $filing_invoice->validationXml = null;
                $filing_invoice->save();

                //esto es para revisar si alguna factura tiene el estado sin validar ps el rips sigue en pendiente por xml
                validateFilingStatus($filing_invoice->filing->id);

                return [
                    'code' => 200,
                    'message' => 'Archivo subido con éxito',
                    'data' => $xmlData,
                ];
            } else {
                $filing_invoice->status_xml = StatusFillingInvoiceEnum::ERROR_XML;
                $filing_invoice->validationXml = json_encode($infoValidation);
                $filing_invoice->save();
            }

            //esto es para revisar si alguna factura tiene el estado sin validar ps el rips sigue en pendiente por xml
            validateFilingStatus($filing_invoice->filing->id);

            return [
                'code' => 200,
                'message' => 'Validaciones finalizadas',
                'infoValidation' => [
                    'validationXml' => json_encode($infoValidation),
                ],
            ];
        });
    }
}
