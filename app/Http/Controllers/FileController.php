<?php

namespace App\Http\Controllers;

use App\Events\FilingInvoiceRowUpdated;
use App\Helpers\Constants;
use App\Http\Requests\File\FileStoreRequest;
use App\Http\Resources\File\FileFormResource;
use App\Http\Resources\File\FileListResource;
use App\Http\Resources\File\FileListTableV2Resource;
use App\Jobs\File\ProcessMassUpload;
use App\Repositories\FileRepository;
use App\Traits\HttpResponseTrait;
use Aws\S3\S3Client;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Throwable;

class FileController extends Controller
{
    use HttpResponseTrait;

    public function __construct(
        protected FileRepository $fileRepository,
    ) {}

    public function list(Request $request)
    {
        try {
            $request['typeData'] = 'all';
            $data = $this->fileRepository->list($request->all());
            $dataFiles = FileListResource::collection($data);

            return [
                'code' => 200,
                'tableData' => $dataFiles,
            ];
        } catch (Throwable $th) {

            return response()->json(['code' => 500, 'message' => 'Error Al Buscar Los Datos', $th->getMessage(), $th->getLine()]);
        }
    }

    public function create()
    {
        try {

            return response()->json([
                'code' => 200,
            ]);
        } catch (Throwable $th) {

            return response()->json(['code' => 500, $th->getMessage(), $th->getLine()]);
        }
    }

    public function edit($id)
    {
        try {

            $file = $this->fileRepository->find($id);

            $file = new FileFormResource($file);

            return response()->json([
                'code' => 200,
                'form' => $file,
            ]);
        } catch (Throwable $th) {

            return response()->json(['code' => 500, $th->getMessage(), $th->getLine()]);
        }
    }

    public function store(FileStoreRequest $request)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->except(['file']);

            if ($request->hasFile('file')) {
                $file = $request->file('file');

                // Define la ruta donde se guardará el archivo
                $modelType = $request->input('fileable_type');
                $modelId = $request->input('fileable_id');
                $path = "companies/company_{$validatedData['company_id']}/{$modelType}/{$modelId}/files";

                $validatedData['fileable_type'] = 'App\\Models\\'.$validatedData['fileable_type'];

                // Guardar el archivo en el almacenamiento de Laravel
                $path = $file->store($path, Constants::DISK_FILES);

                $validatedData['pathname'] = $path;

                $data = $this->fileRepository->store($validatedData);
            }

            DB::commit();

            return response()->json(['code' => 200, 'message' => 'Guardado con exito', 'data' => $data]);
        } catch (Throwable $th) {

            DB::rollBack();

            return response()->json(['code' => 500, 'message' => 'Error Al Guardar', $th->getMessage(), $th->getLine()]);
        }
    }

    public function update(FileStoreRequest $request, $id)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->except(['file']);

            if ($request->hasFile('file')) {
                $file = $request->file('file');

                // Define la ruta donde se guardará el archivo
                $modelType = $request->input('fileable_type');
                $modelId = $request->input('fileable_id');
                $path = "companies/company_{$validatedData['company_id']}/{$modelType}/{$modelId}/files";

                $validatedData['fileable_type'] = 'App\\Models\\'.$validatedData['fileable_type'];

                // Guardar el archivo en el almacenamiento de Laravel
                $path = $file->store($path, Constants::DISK_FILES);

                $validatedData['pathname'] = $path;
            }

            // Actualizar los datos en la base de datos
            $data = $this->fileRepository->store($validatedData);

            DB::commit();

            return response()->json(['code' => 200, 'message' => 'Guardado con exito', 'data' => $data]);
        } catch (Throwable $th) {

            DB::rollBack();

            return response()->json(['code' => 500, 'message' => 'Error Al Guardar', $th->getMessage(), $th->getLine()]);
        }
    }

    public function delete($id)
    {
        try {
            DB::beginTransaction();

            $file = $this->fileRepository->delete($id);
            DB::commit();

            $this->dispatchEventFinal(class_basename($file->fileable_type), $file->fileable_id);

            return response()->json(['code' => 200, 'message' => 'Registro eliminado correctamente']);
        } catch (Throwable $th) {
            DB::rollBack();

            return response()->json([
                'code' => 500,
                'message' => 'Algo Ocurrio, Comunicate Con El Equipo De Desarrollo',
                'error' => $th->getMessage(),
                'line' => $th->getLine(),
            ], 500);
        }
    }

    /**
     * Maneja la descarga de un archivo.
     */
    public function download(Request $request)
    {
        try {
            // Obtiene el nombre del archivo desde el parámetro de consulta
            $file = $request->input('file');

            // Sanitiza el nombre del archivo para eliminar caracteres no válidos
            $sanitizedFileName = preg_replace('/[\/\\\\?%*:|"<>]/', '_', $file);

            // Construye la ruta completa del archivo
            $filePath = storage_path('app/public/'.$file);

            // Verifica si el archivo existe en el almacenamiento
            if (! Storage::exists('public/'.$file)) {
                return response()->json([
                    'code' => 500,
                    'message' => 'El archivo no existe en el almacenamiento',
                ], 500);
            }

            // Verifica si el archivo existe en la ruta construida
            if (! file_exists($filePath)) {
                return response()->json([
                    'code' => 500,
                    'message' => 'El archivo no existe en el sistema de archivos',
                ], 500);
            }

            // Retorna la respuesta de descarga del archivo
            return response()->download($filePath, $sanitizedFileName);
        } catch (\Exception $e) {
            // Maneja cualquier excepción inesperada
            return response()->json([
                'code' => 500,
                'message' => 'Ocurrió un error inesperado: '.$e->getMessage(),
            ], 500);
        }
    }

    public function massUpload(Request $request)
    {
        try {

            if (! $request->hasFile('files')) {
                return response()->json(['code' => 400, 'message' => 'No se encontraron archivos'], 400);
            }

            $company_id = $request->input('company_id');
            $modelType = $request->input('fileable_type');
            $modelId = $request->input('fileable_id');

            // Validar parámetros requeridos
            if (! $company_id || ! $modelType || ! $modelId) {
                return response()->json(['code' => 400, 'message' => 'Faltan parámetros requeridos'], 400);
            }

            $files = $request->file('files');
            $files = is_array($files) ? $files : [$files];
            $fileCount = count($files);
            $uploadId = uniqid();

            // Resolver el modelo completo
            $modelClass = 'App\\Models\\'.$modelType;
            if (! class_exists($modelClass)) {
                return response()->json(['code' => 400, 'message' => 'Modelo no válido'], 400);
            }
            $modelInstance = $modelClass::find($modelId);
            if (! $modelInstance) {
                return response()->json(['code' => 404, 'message' => 'Instancia no encontrada'], 404);
            }

            foreach ($files as $index => $file) {
                $tempPath = $file->store('temp', Constants::DISK_FILES);
                $originalName = $file->getClientOriginalName();
                $extension = $file->getClientOriginalExtension();

                // Construcción dinámica del finalPath pasando todos los parámetros del request
                $buildFile = $this->buildFinalPath(
                    $company_id,
                    $modelType,
                    $modelInstance,
                    $originalName,
                    $request->all(), // Pasamos todos los parámetros
                    $index,
                    $extension
                );

                $data = [
                    'company_id' => $company_id,
                    'fileable_type' => $modelClass,
                    'fileable_id' => $modelId,
                    'support_type_id' => $request->input('support_type_id', null),
                    'channel' => 'filing_invoice.'.$modelId,
                ];

                ProcessMassUpload::dispatch(
                    $tempPath,
                    $buildFile['finalName'],
                    $uploadId,
                    $index + 1,
                    $fileCount,
                    $buildFile['basePath'],
                    $data
                );
            }

            $this->dispatchEventFinal($modelType, $modelId);

            return response()->json([
                'code' => 200,
                'message' => "Se enviaron {$fileCount} archivos a la cola",
                'upload_id' => $uploadId,
                'count' => $fileCount,
            ], 202);
        } catch (\Exception $e) {
            return response()->json(['code' => 500, 'message' => 'Error: '.$e->getMessage()], 500);
        }
    }

    /**
     * Construye la ruta final del archivo según el modelo y parámetros del request
     */
    private function buildFinalPath($company_id, $modelType, $modelInstance, $originalName, $requestParams, $index, $extension)
    {
        // Caso genérico por defecto
        $basePath = "companies/company_{$company_id}/{$modelType}/{$modelInstance->id}/files/{$originalName}";
        $finalName = $originalName;

        // Caso específico para FilingInvoice
        if ($modelType === 'FilingInvoice') {
            // Si existe support_type_id en los parámetros
            if (isset($requestParams['support_type_id']) && isset($requestParams['support_type_code']) && isset($requestParams['third_nit'])) {
                $third_nit = $requestParams['third_nit'];
                $supportName = str_replace(' ', '_', strtoupper($requestParams['support_type_code']));
                $sequentialNumber = str_pad($index + 1, 3, '0', STR_PAD_LEFT);
                $finalName = "{$third_nit}_{$modelInstance->invoice_number}_{$supportName}_{$sequentialNumber}.{$extension}";
                $basePath = "companies/company_{$modelInstance->company->id}/filings/{$modelInstance->filing->type->value}/filing_{$modelInstance->filing->id}/invoices/{$modelInstance->invoice_number}/supports/{$finalName}";
            }
        }

        return [
            'finalName' => $finalName,
            'basePath' => $basePath,
        ];
    }

    private function dispatchEventFinal($modelType, $modelId)
    {
        if ($modelType === 'FilingInvoice') {
            FilingInvoiceRowUpdated::dispatch($modelId);
        }
    }

    public function paginate(Request $request)
    {
        return $this->execute(function () use ($request) {
            $data = $this->fileRepository->paginate($request->all());
            $tableData = FileListTableV2Resource::collection($data);

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

    public function getUrlS3(Request $request)
    {
        return $this->execute(function () use ($request) {
            $s3 = new S3Client([
                'version' => 'latest',
                'region' => config('filesystems.disks.s3.region'),
                'credentials' => [
                    'key' => config('filesystems.disks.s3.key'),
                    'secret' => config('filesystems.disks.s3.secret'),
                ],
            ]);

            // Get the file path from the request
            $fileKey = $request->input('pathname');

            // Validate that the file path is provided
            if (empty($fileKey)) {
                return [
                    'code' => 400,
                    'message' => 'File path (pathname) is required',
                ];
            }

            // Validate that the file exists in S3
            try {
                $exists = $s3->doesObjectExist(
                    config('filesystems.disks.s3.bucket'),
                    $fileKey
                );
                if (! $exists) {
                    return [
                        'code' => 404,
                        'message' => "File not found in S3: {$fileKey}",
                    ];
                }
            } catch (\Exception $e) {
                \Log::error('S3 Existence Check Error: '.$e->getMessage());

                return [
                    'code' => 500,
                    'error' => 'Error checking file existence: '.$e->getMessage(),
                ];
            }

            // Generate a cache key based on the file path
            $cacheKey = 's3_presigned_url_'.md5($fileKey);

            // Check if a valid presigned URL exists in Redis
            $cachedUrlData = Redis::get($cacheKey);
            if ($cachedUrlData) {
                $cachedUrlData = json_decode($cachedUrlData, true);
                if ($cachedUrlData && $cachedUrlData['expires_at'] > now()->timestamp) {
                    return [
                        'code' => 200,
                        'fileUrlS3' => $cachedUrlData['url'],
                    ];
                }
            }

            // Define the duration for the presigned URL
            $duration = '+1 hour'; // You can adjust this (max 7 days with IAM credentials)

            // Generate a new presigned URL
            $cmd = $s3->getCommand('GetObject', [
                'Bucket' => config('filesystems.disks.s3.bucket'),
                'Key' => $fileKey,
            ]);
            $presignedRequest = $s3->createPresignedRequest($cmd, $duration);
            $fileUrlS3 = $presignedRequest->getUri()->__toString();

            // Calculate the expiration timestamp manually
            $expiresAt = Carbon::now()->addHour()->timestamp; // Matches '+1 hour'

            // Prepare the data to cache
            $urlData = [
                'url' => $fileUrlS3,
                'expires_at' => $expiresAt,
            ];

            // Cache the presigned URL in Redis with expiration (in seconds)
            $ttl = 3600; // 1 hour in seconds (matches '+1 hour')
            Redis::setex($cacheKey, $ttl, json_encode($urlData));

            return [
                'code' => 200,
                'fileUrlS3' => $fileUrlS3,
            ];
        });
    }

    public function uploadFiles(Request $request)
    {
        try {
            $request->validate([
                'files.*' => 'required|file|max:209715200',
                'user_id' => 'required',
                'company_id' => 'required',
                'fileable_type' => 'required',
                'fileable_id' => 'required',
                'support_type_id' => 'required',
            ]);

            $user_id = $request->input('user_id');
            $company_id = $request->input('company_id');
            $modelType = $request->input('fileable_type');
            $modelId = $request->input('fileable_id');
            $support_type_id = $request->input('support_type_id');

            // Validar parámetros requeridos
            if (! $user_id || ! $company_id || ! $modelType || ! $modelId) {
                return response()->json(['code' => 400, 'message' => 'Faltan parámetros requeridos'], 400);
            }

            $files = $request->file('files');
            $files = is_array($files) ? $files : [$files];

            // Resolver el modelo completo
            $modelClass = 'App\\Models\\'.$modelType;
            if (! class_exists($modelClass)) {
                return response()->json(['code' => 400, 'message' => 'Modelo no válido'], 400);
            }
            $modelInstance = $modelClass::find($modelId);
            if (! $modelInstance) {
                return response()->json(['code' => 404, 'message' => 'Instancia no encontrada'], 404);
            }

            $uploadedFiles = [];

            foreach ($request->file('files') as $index => $file) {

                $tempPath = $file->store('temp', Constants::DISK_FILES);
                $originalName = $file->getClientOriginalName();
                $extension = $file->getClientOriginalExtension();
                $fileSize = $file->getSize();

                // Construcción dinámica del finalPath pasando todos los parámetros del request
                $buildFile = $this->buildFinalPath(
                    $company_id,
                    $modelType,
                    $modelInstance,
                    $originalName,
                    $request->all(), // Pasamos todos los parámetros
                    $index,
                    $extension
                );

                // Usar la ruta relativa del disco, no la absoluta
                $disk = Constants::DISK_FILES;
                $finalPath = $buildFile['basePath'];
                $fileName = $buildFile['finalName'];

                // Crear el directorio destino si no existe
                $directory = dirname($finalPath);
                if (! Storage::disk($disk)->exists($directory)) {
                    Storage::disk($disk)->makeDirectory($directory);
                }

                // Mover el archivo usando rutas relativas
                $moved = Storage::disk($disk)->move($tempPath, $finalPath);

                if (! $moved) {
                    throw new \Exception('No se pudo mover el archivo');
                }

                $this->fileRepository->store([
                    'user_id' => $user_id,
                    'company_id' => $company_id,
                    'fileable_type' => $modelClass,
                    'fileable_id' => $modelId,
                    'support_type_id' => $support_type_id,
                    'size' => $fileSize,
                    'ext' => $extension,
                    'pathname' => $finalPath,
                    'filename' => $fileName,
                ]);
            }

            return response()->json([
                'success' => true,
                'files' => $uploadedFiles,
            ]);
        } catch (\Exception $e) {
            return response()->json(['code' => 500, 'message' => 'Error: '.$e->getMessage()], 500);
        }
    }
}
