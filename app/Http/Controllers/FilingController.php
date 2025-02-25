<?php

namespace App\Http\Controllers;

use App\Enums\Filing\StatusFilingEnum;
use App\Enums\Filing\StatusFillingInvoiceEnum;
use App\Enums\Filing\TypeFilingEnum;
use App\Events\FilingInvoiceRowUpdated;
use App\Exports\Filing\FilingExcelErrorsValidationExport;
use App\Http\Resources\Filing\FilingInvoiceListResource;
use App\Jobs\File\ProcessMassUpload;
use App\Jobs\Filing\ProcessFilingValidationZip;
use App\Repositories\FilingInvoiceRepository;
use App\Repositories\FilingRepository;
use App\Repositories\SupportTypeRepository;
use App\Repositories\UserRepository;
use App\Services\Redis\TemporaryFilingService;
use App\Traits\HttpTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class FilingController extends Controller
{
    use HttpTrait;

    public function __construct(
        protected UserRepository $userRepository,
        protected FilingRepository $filingRepository,
        protected TemporaryFilingService $tempFilingService,
        protected FilingInvoiceRepository $filingInvoiceRepository,
        protected SupportTypeRepository $supportTypeRepository,
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
            // $this->tempFilingService->saveTemporaryData($filing->id, ["filing" => $filing]);

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

            $excel = Excel::raw(new FilingExcelErrorsValidationExport($data), \Maatwebsite\Excel\Excel::XLSX);

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

            $validationTxt =  json_decode($filing->validationTxt, 1);
            $jsonSuccessfullInvoices = $validationTxt["jsonSuccessfullInvoices"];

            $sumVr = sumVrServicioRips($jsonSuccessfullInvoices);

            $filing = $this->filingRepository->store([
                "id" => $filing_id,
                "sumVr" => $sumVr,
                "contract_id" => $request->input("contract_id"),
            ]);

            //tomamos y hacemos un clon exacto de $jsonSuccessfullInvoices
            $buildDataFinal = json_decode(collect($jsonSuccessfullInvoices), 1);
            //le quitamos al array  general las key que no se deben guardar en json
            eliminarKeysRecursivas($buildDataFinal, ['row', 'file_name']);
            //quitamos los campos que se necesitan por ahora  (numDocumentoIdentificacion,numFEVPagoModerador de de AH , AN,AU)
            deleteFieldsPerzonalizedJson($buildDataFinal);


            //Recorremos las facturas
            foreach ($buildDataFinal as $invoice) {

                //genero y guardo el archivo JSON de la factura
                $nameFile = $invoice['numFactura'] . '.json';
                $routeJson = 'companies/company_' . $filing->company_id . '/filings/' . $filing->type->value . '/filing_' . $filing->id . '/invoices/' . $invoice['numFactura'] . '/' . $nameFile; // Ruta donde se guardará la carpeta
                Storage::disk('public')->put($routeJson, json_encode($invoice)); //guardo el archivo

                // Guardamos la factura y obtenemos el modelo creado
                $filingInvoice = $this->filingInvoiceRepository->store([
                    "filing_id" => $filing_id,
                    "case_number" => $this->filingInvoiceRepository->generateCaseNumber(),
                    "status" => StatusFillingInvoiceEnum::PRE_FILING,
                    "status_xml" => StatusFillingInvoiceEnum::NOT_VALIDATED,
                    "sumVr" => sumVrServicio($invoice),
                    "date" => Carbon::now(),
                    "invoice_number" => $invoice["numFactura"],
                    "users_count" => count($invoice["usuarios"]),
                    "path_json" => $routeJson,
                ]);

                // Guardar los usuarios en una lista de Redis
                $users = $invoice['usuarios'] ?? [];
                $redisKey = "invoice:{$filingInvoice->id}:users"; // Usamos el ID del modelo
                foreach ($users as $user) {
                    Redis::rpush($redisKey, json_encode($user));
                }
                Redis::expire($redisKey, 2592000); // 30 días en segundos (60 * 60 * 24 * 30)
            }

            return [
                'code' => 200,
                'message' => "Radicación actualizada con éxito.",
            ];
        });
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

    public function getDataModalSupportMasiveFiles($filingId)
    {
        return $this->execute(function () use ($filingId) {
            $validInvoiceNumbers = $this->filingInvoiceRepository->validInvoiceNumbers($filingId);
            $validSupportCodes = $this->supportTypeRepository->validInvoiceNumbers($filingId);

            return [
                'code' => 200,
                'validInvoiceNumbers' => $validInvoiceNumbers,
                'validSupportCodes' => $validSupportCodes,
            ];
        });
    }

    public function saveDataModalSupportMasiveFiles(Request $request)
    {
        return $this->execute(function () use ($request) {

            if (!$request->hasFile('files')) {
                return ['code' => 400, 'message' => 'No se encontraron archivos'];
            }

            $company_id = $request->input('company_id');
            $modelType = $request->input('fileable_type');
            $modelId = $request->input('fileable_id');

            // Validar parámetros requeridos
            if (!$company_id || !$modelType || !$modelId) {
                return ['code' => 400, 'message' => 'Faltan parámetros requeridos'];
            }


            $files = $request->file('files');
            $files = is_array($files) ? $files : [$files];
            $fileCount = count($files);
            $uploadId = uniqid();

            // Resolver el modelo completo
            $modelClass = 'App\\Models\\' . $modelType;
            if (!class_exists($modelClass)) {
                return ['code' => 400, 'message' => 'Modelo no válido'];
            }
            $modelInstance = $modelClass::find($modelId);
            $modelInstance->load(["filingInvoice"]);
            if (!$modelInstance) {
                return ['code' => 404, 'message' => 'Instancia no encontrada'];
            }


            $supportTypes = $this->supportTypeRepository->all();


            foreach ($files as $index => $file) {
                $tempPath = $file->store('temp', 'public');
                $originalName = $file->getClientOriginalName();
                $extension = $file->getClientOriginalExtension();

                // Construcción dinámica del finalPath con el nombre del archivo
                $separatedName = explode('_', $originalName);
                list($nit, $numFac, $codeSupport, $consecutive) = $separatedName;


                $invoice = $modelInstance->filingInvoice()->where("invoice_number", $numFac)->first();
                $supportType = $supportTypes->where("code", $codeSupport)->first();

                $supportName = str_replace(' ', '_', strtoupper($codeSupport));
                $finalName = "{$nit}_{$numFac}_{$supportName}_{$consecutive}";
                $finalPath = "companies/company_{$company_id}/filings/{$modelInstance->type->value}/filing_{$modelId}/invoices/{$numFac}/supports/{$finalName}";

                $data = [
                    'company_id' => $company_id,
                    'fileable_type' =>  'App\\Models\\FilingInvoice',
                    'fileable_id' => $invoice->id,
                    'support_type_id' => $supportType->id,
                ];

                ProcessMassUpload::dispatch(
                    $tempPath,
                    $originalName,
                    $uploadId,
                    $index + 1,
                    $fileCount,
                    $finalPath,
                    $data
                );

                FilingInvoiceRowUpdated::dispatch($invoice->id);
            }

            return [
                'code' => 200,
                'message' => "Se enviaron {$fileCount} archivos a la cola",
                'upload_id' => $uploadId,
                'count' => $fileCount
            ];
        }, 202);
    }
}
