<?php

namespace App\Http\Controllers;

use App\Events\FilingInvoiceRowUpdated;
use App\Http\Requests\File\FileStoreRequest;
use App\Http\Resources\File\FileFormResource;
use App\Http\Resources\File\FileListResource;
use App\Jobs\File\ProcessMassUpload;
use App\Repositories\FileRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class FileController extends Controller
{
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

                $validatedData['fileable_type'] = 'App\\Models\\' . $validatedData['fileable_type'];

                // Guardar el archivo en el almacenamiento de Laravel
                $path = $file->store($path, 'public');

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

                $validatedData['fileable_type'] = 'App\\Models\\' . $validatedData['fileable_type'];

                // Guardar el archivo en el almacenamiento de Laravel
                $path = $file->store($path, 'public');

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
            $this->fileRepository->delete($id);
            DB::commit();

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
            $filePath = storage_path('app/public/' . $file);

            // Verifica si el archivo existe en el almacenamiento
            if (! Storage::exists('public/' . $file)) {
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
                'message' => 'Ocurrió un error inesperado: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function massUpload(Request $request)
    {
        try {

            if (!$request->hasFile('files')) {
                return response()->json(['code' => 400, 'message' => 'No se encontraron archivos'], 400);
            }

            $company_id = $request->input('company_id');
            $modelType = $request->input('fileable_type');
            $modelId = $request->input('fileable_id');

            // Validar parámetros requeridos
            if (!$company_id || !$modelType || !$modelId) {
                return response()->json(['code' => 400, 'message' => 'Faltan parámetros requeridos'], 400);
            }

            $files = $request->file('files');
            $files = is_array($files) ? $files : [$files];
            $fileCount = count($files);
            $uploadId = uniqid();

            // Resolver el modelo completo
            $modelClass = 'App\\Models\\' . $modelType;
            if (!class_exists($modelClass)) {
                return response()->json(['code' => 400, 'message' => 'Modelo no válido'], 400);
            }
            $modelInstance = $modelClass::find($modelId);
            if (!$modelInstance) {
                return response()->json(['code' => 404, 'message' => 'Instancia no encontrada'], 404);
            }

            foreach ($files as $index => $file) {
                $tempPath = $file->store('temp', 'public');
                $originalName = $file->getClientOriginalName();
                $extension = $file->getClientOriginalExtension();

                // Construcción dinámica del finalPath pasando todos los parámetros del request
                $finalPath = $this->buildFinalPath(
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
                    'channel' => "filing_invoice.".$modelId,
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
            }

            $this->dispatchEventFinal($modelType, $modelId);




            return response()->json([
                'code' => 200,
                'message' => "Se enviaron {$fileCount} archivos a la cola",
                'upload_id' => $uploadId,
                'count' => $fileCount
            ], 202);
        } catch (\Exception $e) {
            return response()->json(['code' => 500, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }


    /**
     * Construye la ruta final del archivo según el modelo y parámetros del request
     */
    private function buildFinalPath($company_id, $modelType, $modelInstance, $originalName, $requestParams, $index, $extension)
    {
        // Caso genérico por defecto
        $basePath = "companies/company_{$company_id}/{$modelType}/{$modelInstance->id}/files/{$originalName}";

        // Caso específico para FilingInvoice
        if ($modelType === 'FilingInvoice') {
            // Si existe support_type_id en los parámetros
            if (isset($requestParams['support_type_id']) && isset($requestParams['support_type_code']) && isset($requestParams['company_nit'])) {
                $company_nit = $requestParams['company_nit'];
                $supportName = str_replace(' ', '_', strtoupper($requestParams['support_type_code']));
                $sequentialNumber = str_pad($index + 1, 3, '0', STR_PAD_LEFT);
                $finalName = "{$company_nit}_{$modelInstance->invoice_number}_{$supportName}_{$sequentialNumber}.{$extension}";
                $basePath = "companies/company_{$modelInstance->company->id}/filings/{$modelInstance->filing->type->value}/filing_{$modelInstance->filing->id}/invoices/{$modelInstance->invoice_number}/supports/{$finalName}";
            }
        }
        return $basePath;
    }
    private function dispatchEventFinal($modelType, $modelId)
    {
        if ($modelType === 'FilingInvoice') {
            FilingInvoiceRowUpdated::dispatch($modelId);
        }
    }


    public function listExpansionPanel(Request $request)
    {
        try {
            $files = $this->fileRepository->listExpansionPanel($request->all());

            return [
                'code' => 200,
                'files' => $files,
            ];
        } catch (Throwable $th) {

            return response()->json(['code' => 500, 'message' => 'Error Al Buscar Los Datos', $th->getMessage(), $th->getLine()]);
        }
    }
}
