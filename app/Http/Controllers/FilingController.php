<?php

namespace App\Http\Controllers;

use App\Enums\Filing\StatusFilingEnum;
use App\Enums\Filing\StatusFillingInvoiceEnum;
use App\Enums\Filing\TypeFilingEnum;
use App\Events\FilingInvoiceRowUpdated;
use App\Events\FilingProgressEvent;
use App\Exports\Filing\FilingExcelErrorsValidationExport;
use App\Http\Requests\Filing\FilingUploadJsonRequest;
use App\Http\Requests\Filing\FilingUploadZipRequest;
use App\Jobs\File\ProcessMassUpload;
use App\Jobs\Filing\ProcessFilingValidationTxt;
use App\Jobs\Filing\ProcessFilingValidationZip;
use App\Jobs\Filing\ProcessMassXmlUpload;
use App\Models\FilingInvoice;
use App\Repositories\FilingInvoiceRepository;
use App\Repositories\FilingRepository;
use App\Repositories\SupportTypeRepository;
use App\Repositories\UserRepository;
use App\Services\Redis\TemporaryFilingService;
use App\Traits\HttpTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
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

    public function showData($id)
    {
        return $this->execute(function () use ($id) {
            $data = $this->filingRepository->find($id, select: ["id", "type", "contract_id", "validationTxt"]);

            return  $data;
        });
    }

    public function uploadZip(FilingUploadZipRequest $request)
    {
        return $this->runTransaction(function () use ($request) {

            $id = $request->input("id", null);
            $company_id = $request->input("company_id");
            $user_id = $request->input("user_id");
            $type = TypeFilingEnum::RADICATION_OLD;

            //guardo el registro en la bd
            $filing = $this->filingRepository->store([
                'id' =>  $id,
                'company_id' => $company_id,
                'user_id' => $user_id,
                'type' => $type,
                'status' => StatusFilingEnum::IN_PROCESS,
            ]);

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

    public function updateValidationTxt($id)
    {
        return $this->runTransaction(function () use ($id) {

            $this->filingRepository->changeState($id, null, "validationTxt");

            return [
                'code' => 200,
                'message' => "Registro actualizado con éxito.",
            ];
        });
    }

    public function updateContract(Request $request)
    {
        return $this->runTransaction(function () use ($request) {

            $filing_id = $request->input("filing_id");
            $contract_id = $request->input("contract_id");

            $filing = $this->filingRepository->find($filing_id);

            $validationTxt = json_decode($filing->validationTxt, 1);
            $jsonSuccessfullInvoices = $validationTxt["jsonSuccessfullInvoices"];
            $errorMessages = collect($validationTxt["errorMessages"]);

            $sumVr = sumVrServicioRips($jsonSuccessfullInvoices);

            $filing = $this->filingRepository->store([
                "id" => $filing_id,
                "sumVr" => $sumVr,
                "contract_id" => $contract_id,
                "validationTxt" => null,
            ]);

            //tomamos y hacemos un clon exacto de $jsonSuccessfullInvoices
            $buildDataFinal = json_decode(collect($jsonSuccessfullInvoices), 1);
            //le quitamos al array  general las key que no se deben guardar en json
            eliminarKeysRecursivas($buildDataFinal, ['row', 'file_name']);
            //quitamos los campos que se necesitan por ahora  (numDocumentoIdentificacion,numFEVPagoModerador de de AH , AN,AU)
            deleteFieldsPerzonalizedJson($buildDataFinal);

            //Recorremos las facturas
            foreach ($buildDataFinal as $invoice) {

                // Buscar los mensajes de error de la factura
                $errorMessagesInvoice = $errorMessages->where("num_invoice", $invoice["numFactura"])->values();

                //genero y guardo el archivo JSON de la factura
                $nameFile = $invoice['numFactura'] . '.json';
                $routeJson = 'companies/company_' . $filing->company_id . '/filings/' . $filing->type->value . '/filing_' . $filing->id . '/invoices/' . $invoice['numFactura'] . '/' . $nameFile; // Ruta donde se guardará la carpeta
                Storage::disk('public')->put($routeJson, json_encode($invoice)); //guardo el archivo

                // Guardamos la factura y obtenemos el modelo creado
                $filingInvoice = $this->filingInvoiceRepository->store([
                    "filing_id" => $filing_id,
                    "status" => StatusFillingInvoiceEnum::PRE_FILING,
                    "status_xml" => StatusFillingInvoiceEnum::NOT_VALIDATED,
                    "sumVr" => sumVrServicio($invoice),
                    "date" => Carbon::now(),
                    "invoice_number" => $invoice["numFactura"],
                    "users_count" => count($invoice["usuarios"]),
                    "path_json" => $routeJson,
                    "validationTxt" => json_encode($errorMessagesInvoice->all()),
                ]);
            }

            return [
                'code' => 200,
                'message' => "Radicación actualizada con éxito.",
            ];
        });
    }

    public function getDataModalSupportMasiveFiles($filingId)
    {
        return $this->execute(function () use ($filingId) {
            $validInvoiceNumbers = $this->filingInvoiceRepository->validInvoiceNumbers($filingId);
            $validSupportCodes = $this->supportTypeRepository->validSupportCodes();

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
                    'fileable_type' => 'App\\Models\\FilingInvoice',
                    'fileable_id' => $invoice->id,
                    'support_type_id' => $supportType->id,
                    'channel' => "filingSupport.{$modelId}",
                ];

                ProcessMassUpload::dispatch(
                    $tempPath,
                    $finalName,
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

    public function uploadJson(FilingUploadJsonRequest $request)
    {
        return $this->runTransaction(function () use ($request) {

            // Preparar datos iniciales
            $id = $request->input("id", null);
            $company_id = $request->input("company_id");
            $user_id = $request->input("user_id");

            $files = $request->file('files');
            $files = is_array($files) ? $files : [$files];
            $totalFiles = count($files);
            $uploadId = uniqid();
            $chunkSize = (int) env('CHUNKSIZE', 10);

            // Guardar registro inicial
            $filing = $this->filingRepository->store([
                'id' =>  $id,
                'company_id' => $company_id,
                'user_id' => $user_id,
                'type' => TypeFilingEnum::RADICATION_2275,
                'status' => StatusFilingEnum::IN_PROCESS,
            ]);

            $processedFiles = 0;

            // Procesar cada archivo
            foreach ($files as $index => $file) {
                try {
                    // Almacenar temporalmente y obtener info
                    $tempPath = $file->store('temp', 'public');
                    $originalName = $file->getClientOriginalName();

                    // Leer JSON
                    $jsonData = openFileJson($tempPath);
                    $jsonData = normalizeJsonData($jsonData);


                    if (empty($jsonData)) {
                        continue; // Saltar si el archivo está vacío
                    }

                    // Dividir en pedazos
                    $partitions = array_chunk($jsonData, $chunkSize);
                    $totalPartitions = count($partitions);
                    $lastIndex = $totalPartitions - 1;

                    // Procesar pedazos
                    foreach ($partitions as $partitionIndex => $chunk) {
                        $isLast = ($partitionIndex === $lastIndex);

                        ProcessFilingValidationTxt::dispatch(
                            $filing->id,
                            $chunk,
                            $user_id,
                            $isLast && ($index === $totalFiles - 1) // Solo último pedazo del último archivo
                        );
                    }

                    // Actualizar progreso por archivo procesado
                    $processedFiles++;
                    $progress = ($processedFiles / $totalFiles) * 100;

                    // Actualizar registro y emitir evento
                    FilingProgressEvent::dispatch($filing->id, $progress);
                } catch (\Exception $e) {
                    // Registrar error y continuar
                    \Log::error("Error procesando archivo {$originalName}: " . $e->getMessage());
                    continue;
                }
            }


            return $filing;
        });
    }

    public function getDataModalXmlMasiveFiles($filingId)
    {
        return $this->execute(function () use ($filingId) {
            $validInvoiceNumbers = $this->filingInvoiceRepository->validInvoiceNumbers($filingId);

            return [
                'code' => 200,
                'validInvoiceNumbers' => $validInvoiceNumbers,
            ];
        });
    }

    public function saveDataModalXmlMasiveFiles(Request $request)
    {
        return $this->execute(function () use ($request) {

            if (!$request->hasFile('files')) {
                return ['code' => 400, 'message' => 'No se encontraron archivos'];
            }

            $company_id = $request->input("company_id");
            $third_nit = $request->input("third_nit");
            $filing_id = $request->input('filing_id');

            // Validar parámetros requeridos
            if (!$company_id || !$third_nit || !$filing_id) {
                return ['code' => 400, 'message' => 'Faltan parámetros requeridos'];
            }

            $files = $request->file('files');
            $files = is_array($files) ? $files : [$files];
            $fileCount = count($files);
            $uploadId = uniqid();

            foreach ($files as $index => $file) {
                $tempPath = $file->store('temp', 'public');
                $originalName = $file->getClientOriginalName();

                $data = [
                    'company_id' => $company_id,
                    'third_nit' => $third_nit,
                    'filing_id' => $filing_id,
                    'tempPath' => $tempPath,
                    'originalName' => $originalName,
                    'uploadId' => $uploadId,
                    'fileNumber' => $index + 1,
                    'totalFiles' => $fileCount,
                    'channel' => "filingXml.{$filing_id}",
                ];

                ProcessMassXmlUpload::dispatch($data);
            }

            return [
                'code' => 200,
                'message' => "Se enviaron {$fileCount} archivos a la cola",
                'upload_id' => $uploadId,
                'count' => $fileCount
            ];
        }, 202);
    }

    public function getValidationTxtByFilingId($filing_id)
    {
        // Consultar todos los registros que coincidan con el filing_id
        $filingInvoices = FilingInvoice::where('filing_id', $filing_id)->select('validationTxt')->get();

        // Inicializar un arreglo vacío para almacenar todos los validationTxt
        $combinedValidationTxt = [];

        // Iterar sobre cada registro y unir los arreglos validationTxt
        foreach ($filingInvoices as $invoice) {
            // Decodificar el campo validationTxt de JSON a un arreglo
            $validationTxtArray = json_decode($invoice->validationTxt, true);

            // Unir el arreglo decodificado al arreglo combinado
            $combinedValidationTxt = array_merge($combinedValidationTxt, $validationTxtArray);
        }

        return $combinedValidationTxt;
    }
}
