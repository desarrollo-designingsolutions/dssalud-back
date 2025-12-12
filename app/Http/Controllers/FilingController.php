<?php

namespace App\Http\Controllers;

use App\Enums\Filing\StatusFilingEnum;
use App\Enums\Filing\StatusFilingInvoiceEnum;
use App\Enums\Filing\TypeFilingEnum;
use App\Enums\Service\TypeServiceEnum;
use App\Events\FilingInvoiceRowUpdated;
use App\Events\FilingRowUpdatedNow;
use App\Exports\Filing\FilingExcelErrorsValidationExport;
use App\Exports\Filing\FilingInvoiceExcelErrorsValidationExport;
use App\Helpers\Constants;
use App\Http\Requests\Filing\FilingUploadJsonRequest;
use App\Http\Requests\Filing\FilingUploadZipRequest;
use App\Http\Resources\Filing\FilingPaginateResource;
use App\Jobs\File\ProcessMassUpload;
use App\Jobs\Filing\ProcessFilingValidationTxt;
use App\Jobs\Filing\ProcessFilingValidationZip;
use App\Jobs\Filing\ProcessMassXmlUpload;
use App\Models\InvoiceAudit;
use App\Models\Patient;
use App\Models\Service;
use App\Notifications\BellNotification;
use App\Repositories\FilingInvoiceRepository;
use App\Repositories\FilingRepository;
use App\Repositories\SupportTypeRepository;
use App\Repositories\UserRepository;
use App\Traits\HttpResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Str;
use App\Traits\ImportHelper;

class FilingController extends Controller
{
    use HttpResponseTrait, ImportHelper;

    public function __construct(
        protected UserRepository $userRepository,
        protected FilingRepository $filingRepository,
        protected FilingInvoiceRepository $filingInvoiceRepository,
        protected SupportTypeRepository $supportTypeRepository,
    ) {
    }

    public function paginate(Request $request)
    {
        return $this->execute(function () use ($request) {

            $filings = $this->filingRepository->paginate($request->all());
            $listRips = FilingPaginateResource::collection($filings);

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

    public function showData($id)
    {
        return $this->execute(function () use ($id) {
            $data = $this->filingRepository->find($id, select: ['id', 'type', 'contract_id', 'validationTxt', 'status']);

            return $data;
        });
    }

    public function uploadZip(FilingUploadZipRequest $request)
    {
        return $this->runTransaction(function () use ($request) {

            $id = $request->input('id', null);
            $company_id = $request->input('company_id');
            $user_id = $request->input('user_id');
            $type = TypeFilingEnum::FILING_TYPE_001;

            // guardo el registro en la bd
            $filing = $this->filingRepository->store([
                'id' => $id,
                'company_id' => $company_id,
                'user_id' => $user_id,
                'type' => $type,
                'status' => StatusFilingEnum::FILING_EST_001,
            ]);

            if ($request->hasFile('archiveZip')) {
                $file = $request->file('archiveZip');
                $ruta = '/companies/company_' . $company_id . '/filings/' . $type->value . '/filing_' . $filing->id; // Ruta donde se guardará la carpeta
                $nombreArchivo = $file->getClientOriginalName(); // Obtiene el nombre original del archivo
                $path_zip = $file->storeAs($ruta, $nombreArchivo, Constants::DISK_FILES); // Guarda el archivo con el nombre original
                $filing->path_zip = $path_zip;
                $filing->save();
            }

            $auth = $this->userRepository->find($user_id);

            // VALIDACION ZIP
            ProcessFilingValidationZip::dispatch($filing->id, $auth, $company_id);

            return $filing;
        }, debug: false);
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
                'message' => 'Registro eliminado con éxito.',
            ];
        });
    }

    public function updateValidationTxt($id)
    {
        return $this->runTransaction(function () use ($id) {

            $this->filingRepository->changeState($id, null, 'validationTxt');
            $this->filingRepository->changeState($id, null, 'validationZip');

            return [
                'code' => 200,
                'message' => 'Registro actualizado con éxito.',
            ];
        });
    }

    public function updateContract(Request $request)
    {
        return $this->runTransaction(function () use ($request) {

            $filing_id = $request->input('filing_id');
            $contract_id = $request->input('contract_id');

            $filing = $this->filingRepository->find($filing_id);

            $validationTxt = json_decode($filing->validationTxt, 1);

            if ($filing->type == TypeFilingEnum::FILING_TYPE_001) {

                $filing = $this->filingRepository->store([
                    'id' => $filing_id,
                    'sumVr' => 0,
                    'contract_id' => $contract_id,
                ]);

                $buildDataFinal = openFileJson($filing->path_json);

                // Sumatoria de los valores de los servicios
                $sumVrServicios = $filing->sumVr;

                // Recorremos las facturas
                foreach ($buildDataFinal as $invoice) {

                    // genero y guardo el archivo JSON de la factura
                    $nameFile = $invoice[Constants::KEY_NUMFACT] . '.json';
                    $routeJson = 'companies/company_' . $filing->company_id . '/filings/' . $filing->type->value . '/filing_' . $filing->id . '/invoices/' . $invoice[Constants::KEY_NUMFACT] . '/' . $nameFile; // Ruta donde se guardará la carpeta
                    Storage::disk(Constants::DISK_FILES)->put($routeJson, json_encode($invoice)); // guardo el archivo

                    $sumTotalServices = sumVrServicio($invoice);

                    $sumVrServicios += $sumTotalServices;

                    // Guardamos la factura y obtenemos el modelo creado
                    $filingInvoice = $this->filingInvoiceRepository->store([
                        'filing_id' => $filing_id,
                        'status' => StatusFilingInvoiceEnum::FILINGINVOICE_EST_001,
                        'status_xml' => StatusFilingInvoiceEnum::FILINGINVOICE_EST_004,
                        'sumVr' => $sumTotalServices,
                        'date' => Carbon::now(),
                        'invoice_number' => $invoice[Constants::KEY_NUMFACT],
                        'users_count' => count($invoice['usuarios']),
                        'path_json' => $routeJson,
                    ]);
                }

                $filing->sumVr = $sumVrServicios;
                $filing->save();
            } elseif ($filing->type == TypeFilingEnum::FILING_TYPE_002) {
                $jsonSuccessfullInvoices = $validationTxt['jsonSuccessfullInvoices'];
                $errorMessages = collect($validationTxt['errorMessages']);

                $sumVr = $filing->sumVr;

                $sumVr += sumVrServicios($jsonSuccessfullInvoices);
                $filing = $this->filingRepository->store([
                    'id' => $filing_id,
                    'sumVr' => $sumVr,
                    'contract_id' => $contract_id,
                    'validationTxt' => null,
                ]);

                // tomamos y hacemos un clon exacto de $jsonSuccessfullInvoices
                $buildDataFinal = json_decode(collect($jsonSuccessfullInvoices), 1);
                // le quitamos al array  general las key que no se deben guardar en json
                eliminarKeysRecursivas($buildDataFinal, ['row', 'file_name']);
                // quitamos los campos que se necesitan por ahora  (numDocumentoIdentificacion,numFEVPagoModerador de de AH , AN,AU)
                deleteFieldsPerzonalizedJson($buildDataFinal);

                // Recorremos las facturas
                foreach ($buildDataFinal as $invoice) {

                    // Buscar los mensajes de error de la factura
                    $errorMessagesInvoice = $errorMessages->where('num_invoice', $invoice['numFactura'])->values();

                    // genero y guardo el archivo JSON de la factura
                    $nameFile = $invoice['numFactura'] . '.json';
                    $routeJson = 'companies/company_' . $filing->company_id . '/filings/' . $filing->type->value . '/filing_' . $filing->id . '/invoices/' . $invoice['numFactura'] . '/' . $nameFile; // Ruta donde se guardará la carpeta
                    Storage::disk(Constants::DISK_FILES)->put($routeJson, json_encode($invoice)); // guardo el archivo

                    // Guardamos la factura y obtenemos el modelo creado
                    $filingInvoice = $this->filingInvoiceRepository->store([
                        'filing_id' => $filing_id,
                        'status' => StatusFilingInvoiceEnum::FILINGINVOICE_EST_001,
                        'status_xml' => StatusFilingInvoiceEnum::FILINGINVOICE_EST_004,
                        'sumVr' => sumVrServicio($invoice),
                        'date' => Carbon::now(),
                        'invoice_number' => $invoice['numFactura'],
                        'users_count' => count($invoice['usuarios']),
                        'path_json' => $routeJson,
                        'validationTxt' => json_encode($errorMessagesInvoice->all()),
                    ]);
                }
            }

            return [
                'code' => 200,
                'message' => 'Radicación actualizada con éxito.',
            ];
        }, debug: false);
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
            $modelInstance->load(['filingInvoice']);
            if (!$modelInstance) {
                return ['code' => 404, 'message' => 'Instancia no encontrada'];
            }

            $supportTypes = $this->supportTypeRepository->all();

            foreach ($files as $index => $file) {
                $tempPath = $file->store('temp', Constants::DISK_FILES);
                $originalName = $file->getClientOriginalName();
                $extension = $file->getClientOriginalExtension();

                // Construcción dinámica del finalPath con el nombre del archivo
                $separatedName = explode('_', $originalName);
                [$nit, $numFac, $codeSupport, $consecutive] = $separatedName;

                $invoice = $modelInstance->filingInvoice()->where('invoice_number', $numFac)->first();
                $supportType = $supportTypes->where('code', $codeSupport)->first();

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
                'count' => $fileCount,
            ];
        }, 202);
    }

    public function uploadJson(FilingUploadJsonRequest $request)
    {
        return $this->runTransaction(function () use ($request) {

            // Preparar datos iniciales
            $id = $request->input('id', null);
            $company_id = $request->input('company_id');
            $user_id = $request->input('user_id');

            $files = $request->file('files');
            $files = is_array($files) ? $files : [$files];
            $totalFiles = count($files);

            // Guardar registro inicial
            $filing = $this->filingRepository->store([
                'id' => $id,
                'company_id' => $company_id,
                'user_id' => $user_id,
                'type' => TypeFilingEnum::FILING_TYPE_002,
                'status' => StatusFilingEnum::FILING_EST_001,
            ]);

            $processedFiles = 0;

            // Procesar cada archivo
            $lastIndex = count($files) - 1;
            foreach ($files as $index => $file) {
                try {
                    // Almacenar temporalmente y obtener info
                    $tempPath = $file->store('temp', Constants::DISK_FILES);
                    $originalName = $file->getClientOriginalName();

                    // Leer JSON
                    $jsonData = openFileJson($tempPath);

                    if (empty($jsonData)) {
                        continue; // Saltar si el archivo está vacío
                    }

                    // Actualizar progreso por archivo procesado
                    $processedFiles++;
                    $progress = ($processedFiles / $totalFiles) * 100;
                    $lastFile = $lastIndex == $index;

                    ProcessFilingValidationTxt::dispatch($filing->id, $jsonData, $lastFile);
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

            $company_id = $request->input('company_id');
            $third_nit = $request->input('third_nit');
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
                $tempPath = $file->store('temp', Constants::DISK_FILES);
                $originalName = $file->getClientOriginalName();

                // Obtener la ruta completa del archivo en el servidor
                $tempPath = Storage::disk(Constants::DISK_FILES)->path($tempPath);

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
                'count' => $fileCount,
            ];
        }, 202);
    }

    public function getAllValidation($filing_id)
    {
        return $this->execute(function () use ($filing_id) {
            // Consultar todos los registros que coincidan con el filing_id
            $errorMessages = $this->filingRepository->getAllValidation($filing_id);

            return [
                'code' => 200,
                'errorMessages' => $errorMessages,
            ];
        });
    }

    public function excelAllValidation(Request $request)
    {
        return $this->execute(function () use ($request) {

            // Obtener los mensajes de errores de las validaciones
            $data = $this->filingRepository->getAllValidation($request->input('id'));

            $excel = Excel::raw(new FilingInvoiceExcelErrorsValidationExport($data), \Maatwebsite\Excel\Excel::XLSX);

            $excelBase64 = base64_encode($excel);

            return [
                'code' => 200,
                'excel' => $excelBase64,
            ];
        });
    }

    public function getCountFilingInvoicePreRadicated($id)
    {
        return $this->runTransaction(function () use ($id) {

            $countData = $this->filingRepository->getCountFilingInvoicePreRadicated($id);
            $countDataWithOutSupports = $this->filingRepository->getCountFilingInvoiceWithOutSupports($id);

            return [
                'code' => 200,
                'countData' => $countData,
                'countDataWithOutSupports' => $countDataWithOutSupports,
            ];
        });
    }

    public function changeStatusFilingInvoicePreRadicated(Request $request)
    {
        return $this->runTransaction(function () use ($request) {

            $post = $request->all();

            return $this->orquestadorCargueMasiva($post);

            $data = $this->filingRepository->changeStatusFilingInvoicePreRadicated($post['filing_id']);
            $this->filingRepository->changeState($post['filing_id'], StatusFilingEnum::FILING_EST_009, 'status');

            FilingRowUpdatedNow::dispatch($post['filing_id']);

            // Enviar notificación
            $user = $this->userRepository->find($post['user_id']);
            if ($user) {
                $user->notify(new BellNotification([
                    'title' => 'Radicación finalizada',
                    'subtitle' => 'Se ha finalizado la radicación con éxito.',
                ]));
            }

            return [
                'code' => 200,
                'data' => $data,
            ];
        });
    }

    public function orquestadorCargueMasiva($post)
    {
        $this->startBenchmark('123');
        $pathJsons = $this->cargueInvoiceAudit($post);
        $this->endBenchmark('123');
    }

    public function cargueInvoiceAudit($post)
    {
        $filing = $this->filingRepository->find($post['filing_id'], ['filingInvoiceRadicateds']);

        $chunkData = array_chunk($filing->filingInvoiceRadicateds->toArray(), 5);
        $dataMasive = [];
        $dataMasiveJson = [];

        foreach ($chunkData as $chunk) {
            foreach ($chunk as $key => $filingInvoice) {
                $uuid = (string) Str::uuid();
                $dataMasiveJson[] = [
                    'id' => $uuid,
                    'path_json' => $filingInvoice['path_json'],
                ];
                $dataMasive[] = [
                    'id' => $uuid,
                    'company_id' => $filing->company_id,
                    'third_id' => $filing->contract?->third_id,
                    'filing_invoice_id' => $filingInvoice['id'],
                    'invoice_number' => $filingInvoice['invoice_number'],
                    'total_value' => $filingInvoice['sumVr'],
                    'origin' => "??",
                    'expedition_date' => null, //preguntar a carlos andres que fecha es esta
                    'date_entry' => null, //preguntar a carlos andres que fecha es esta
                    'date_departure' => null, //preguntar a carlos andres que fecha es esta
                    'modality' => "??",

                    'regimen' => "??",
                    'coverage' => "??",
                    'contract_number' => $filing->contract?->name, //preguntar a carlos andres si este es el nombre o que campo es del contrato
                ];
            }
            InvoiceAudit::insert($dataMasive);
            $data = $this->carguePatients($post, $dataMasiveJson);
            $dataMasive = [];
            $dataMasiveJson = [];
        }

        return $dataMasiveJson;
    }

    public function carguePatients($post, $dataMasiveJson)
    {
        foreach ($dataMasiveJson as $data) {
            $invoice = openFileJson($data['path_json']);
            $company_id = $post['company_id'];

            
            $dataPatients = [];
            $dataServices = [];
            foreach ($invoice['usuarios'] as $user) {
                $uuid = (string) Str::uuid();
                $dataServices[] = [
                    'company_id' => $company_id,
                    'invoice_audit_id' => $data['id'],
                    'patient_id' => $uuid,
                    'services' => $user['servicios'],
                ];
                $dataPatients[] = [
                    'id' => $uuid,
                    'company_id' => $company_id,
                    'invoice_audit_id' => $data['id'],
                    'type_identification' => $user['Tipo_de_identificacion_del_usuario'] ?? '',
                    'identification_number' => $user['numDocumentoIdentificacion'] ?? '',
                    'first_name' => $user['Primer_nombre_del_usuario'] ?? '',
                    'second_name' => $user['Segundo_nombre_del_usuario'] ?? '',
                    'first_surname' => $user['Primer_apellido_del_usuario'] ?? '',
                    'second_surname' => $user['Segundo_apellido_del_usuario'] ?? '',
                    'gender' => $user['Sexo'] ?? '',
                ];
            }
            $chunkData = array_chunk($dataPatients, 5);
            $chunkDataServices = array_chunk($dataServices, 5);

            foreach ($chunkData as $key => $chunk) {
                Patient::insert($chunk);
                $data = $this->cargueServices($chunkDataServices[$key]);
            }
        }

        return false;
    }

    public function cargueServices($dataServices)
    {
        foreach ($dataServices as $data) {
            $company_id = $data['company_id'];

            $dataServicesDataBase = [];
            foreach ($data['services'] as $key => $service) {
                foreach ($service as $k => $value) {
                    
                    // necesito comparar $key con el enum TypeServiceEnum para saber a que tipo de servicio corresponde
                    // Pero debo buscar por el dato de elementJson, si coincide que me de el valor del enum
                    $typeService = TypeServiceEnum::fromElementJson($key);
                    
                    //si el tipo de servicio es procedimientos, guardar en la tabla procedures
                    if ($typeService === TypeServiceEnum::SERVICE_TYPE_002) {

                        
                    }
                    

                    // esta es la parte de los servicios de procedimientos, de querer guardar otros servicios se debe tener mas datos para probar
                    $dataServicesDataBase[] = [
                        'id' => (string) Str::uuid(),
                        'company_id' => $company_id,
                        'invoice_audit_id' => $data['invoice_audit_id'],
                        'patient_id' => $data['patient_id'],
                        'detail_code' => $value['Codigo_del_procedimiento'] ?? null,
                        'type' => $typeService->value,
                        'serviceable_type' => $typeService->model(),
                        'serviceable_id' => '',
                        'description' => $typeService->value,
                        'quantity' => null,
                        'unit_value' => null,
                        'total_value' => $value['vrServicio'] ?? 0,
                        'value_glosa' => null,
                        'value_approved' => null,
                    ];
                }
                $chunkData = array_chunk($dataServicesDataBase, 5);
    
                foreach ($chunkData as $chunk) {
                    Service::insert($chunk);
                }
            }
        }

        return false;
    }
}
