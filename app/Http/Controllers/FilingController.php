<?php

namespace App\Http\Controllers;

use App\Enums\Filing\StatusFilingEnum;
use App\Enums\Filing\StatusFillingInvoiceEnum;
use App\Enums\Filing\TypeFilingEnum;
use App\Exports\Filing\FilingExcelErrorsValidationExport;
use App\Http\Resources\Filing\FilingInvoiceListResource;
use App\Jobs\Filing\ProcessFilingValidationZip;
use App\Repositories\FilingInvoiceRepository;
use App\Repositories\FilingRepository;
use App\Repositories\UserRepository;
use App\Services\Redis\TemporaryFilingService;
use App\Traits\HttpTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class FilingController extends Controller
{
    use HttpTrait;

    public function __construct(
        protected UserRepository $userRepository,
        protected FilingRepository $filingRepository,
        protected TemporaryFilingService $tempFilingService,
        protected FilingInvoiceRepository $filingInvoiceRepository,
    ) {}

    public function uploadZip(Request $request)
    {
        return $this->runTransaction(function () use ($request) {

            $company_id = $request->input("company_id");
            $user_id = $request->input("user_id");
            $type = TypeFilingEnum::RADICATION_OLD;

            //guardo el registro en la bd
            $filing = $this->filingRepository->store([
                'company_id' =>  $company_id,
                'user_id' => $user_id,
                'type' => $type,
                'status' => StatusFilingEnum::IN_PROCESS,

            ]);

            //guardo temporalmente el valor en redis
            $this->tempFilingService->saveTemporaryData($filing->id, ["filing" => $filing]);

            if ($request->hasFile('archiveZip')) {
                $file = $request->file('archiveZip');
                $ruta = '/companies/company_' . $company_id . '/filings/' . $type->value . '/filing_' . $filing->id; // Ruta donde se guardará la carpeta
                $nombreArchivo = $file->getClientOriginalName(); // Obtiene el nombre original del archivo
                $path_zip = $file->storeAs($ruta, $nombreArchivo, 'public'); // Guarda el archivo con el nombre original
                $filing->path_zip = $path_zip;
                $filing->save();
            }

            $auth = $this->userRepository->find($user_id);

            //VALIDACION ZIP
            ProcessFilingValidationZip::dispatch($filing->id, $auth, $company_id);

            return $filing;
        });
    }

    public function showErrorsValidation(Request $request)
    {
        return $this->execute(function () use ($request) {

            // Obtener los mensajes de errores de las validaciones
            $data = $this->filingRepository->getValidationsErrorMessages($request->input('id'));

            return [
                'code' => 200,
                ...$data,
            ];
        });
    }

    public function excelErrorsValidation(Request $request)
    {
        return $this->execute(function () use ($request) {

            // Obtener los mensajes de errores de las validaciones
            $data = $this->filingRepository->getValidationsErrorMessages($request->input('id'));

            $excel = Excel::raw(new FilingExcelErrorsValidationExport($data["errorMessages"]), \Maatwebsite\Excel\Excel::XLSX);

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

            $data = $this->filingRepository->find($id);

            if ($data) {
                $data->delete();
            }


            return [
                'code' => 200,
                'message' => "Registro eliminado con éxito.",
            ];
        });
    }

    public function updateContract(Request $request)
    {
        return $this->runTransaction(function () use ($request) {

            $filing_id = $request->input("filing_id");

            $filing = $this->filingRepository->find($filing_id);

            // $tempFiling =  $this->tempFilingService->getTemporaryData($filing_id);

            $validationTxt =  json_decode($filing->validationTxt, 1);
            $jsonSuccessfullInvoices = $validationTxt["jsonSuccessfullInvoices"];


            $sumVr = sumVrServicioRips($jsonSuccessfullInvoices);

            $filing = $this->filingRepository->store([
                "id" => $filing_id,
                "sumVr" => $sumVr,
                "contract_id" => $request->input("contract_id"),
            ]);


            foreach ($jsonSuccessfullInvoices as $key => $invoice) {
                $this->filingInvoiceRepository->store([
                    "filing_id" => $filing_id,
                    "invoice_number" => $invoice["numFactura"],
                    "case_number" => $key + 1,
                    "status" => StatusFillingInvoiceEnum::PRE_FILING,
                    "status_xml" => StatusFillingInvoiceEnum::NOT_VALIDATED,
                    "sumVr" => sumVrServicio($invoice),
                    "date" => Carbon::now(),
                ]);
            }

            return [
                'code' => 200,
                'message' => "Radicación actualizada con éxito.",
            ];
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
                    "title" => "Facturas re-radicadas",
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
}
