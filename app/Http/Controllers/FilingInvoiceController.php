<?php

namespace App\Http\Controllers;

use App\Enums\Filing\StatusFillingInvoiceEnum;
use App\Http\Resources\Filing\FilingInvoiceListResource;
use App\Repositories\FilingInvoiceRepository;
use App\Repositories\FilingRepository;
use App\Repositories\SupportTypeRepository;
use App\Repositories\UserRepository;
use App\Services\Redis\TemporaryFilingService;
use App\Traits\HttpTrait;
use Illuminate\Http\Request;

class FilingInvoiceController extends Controller
{
    use HttpTrait;

    public function __construct(
        protected UserRepository $userRepository,
        protected FilingRepository $filingRepository,
        protected TemporaryFilingService $tempFilingService,
        protected FilingInvoiceRepository $filingInvoiceRepository,
        protected SupportTypeRepository $supportTypeRepository,
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

            // $company_id = $request->input("company_id");
            // $user_id = $request->input("user_id");

            // if ($request->hasFile('archiveXML')) {
            //     $file = $request->file('archiveXML');
            //     $ruta = '/companies/company_' . $company_id . '/filings/' . $type->value . '/filing_' . $filing->id; // Ruta donde se guardará la carpeta
            //     $nombreArchivo = $file->getClientOriginalName(); // Obtiene el nombre original del archivo
            //     $path_zip = $file->storeAs($ruta, $nombreArchivo, 'public'); // Guarda el archivo con el nombre original

            //     $filing->path_zip = $path_zip;
            //     $filing->save();
            // }

            // $auth = $this->userRepository->find($user_id);

            // //VALIDACION XML
            // ProcessFilingValidationXML::dispatch($filing->id, $auth, $company_id);

            // return $filing;
        });
    }
}
