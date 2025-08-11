<?php

use App\Http\Controllers\ConciliationController;
use App\Jobs\File\ProcessMassUpload;
use App\Jobs\ProcessInvoiceAuditCounts;
use App\Models\Company;
use App\Models\SupportType;
use App\Models\User;
use App\Notifications\BellNotification;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

Route::get('/pruebaRedisExcel', function () {
    // Obtener todas las claves de Redis
    $redisKeys = Redis::connection('redis_6380')->keys('invoice_audit:*:db_count');

    // Crear una nueva hoja de cálculo
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();

    // Agregar encabezados
    $sheet->setCellValue('A1', 'UUID');

    // Procesar las claves para extraer el UUID
    foreach ($redisKeys as $index => $key) {
        // Extraer el UUID (la parte entre 'invoice_audit:' y ':db_count')
        $uuid = explode(':', $key)[1];
        $sheet->setCellValue('A'.($index + 2), $uuid);
    }

    // Crear una respuesta para descargar el archivo Excel
    $response = new StreamedResponse(function () use ($spreadsheet) {
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
    });

    $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $response->headers->set('Content-Disposition', 'attachment;filename="redis_uuids.xlsx"');
    $response->headers->set('Cache-Control', 'max-age=0');

    return $response;
});

Route::get('/pruebaRedis', function () {
    // Despachar el trabajo ProcessInvoiceAuditCounts
    $job = ProcessInvoiceAuditCounts::dispatch()->onQueue('imports_2');

    // Retornar una respuesta al usuario
    return response()->json([
        'message' => 'El procesamiento ha comenzado en segundo plano.',
    ]);
});
Route::get('/phpinfo', function () {
    phpinfo();
});
Route::get('/', function () {

    // $user = User::find("9e601862-728e-42a1-9efb-b46efaf731ba");

    // // Enviar notificación
    // $user->notify(new BellNotification([
    //     'title' => "hola",
    //     'subtitle' => "chao",
    // ]));

    return view('welcome');
});

// // Incluir rutas personalizadas
require __DIR__.'/reconciliationGroupWeb.php';

Route::get('/s3-test/{folder?}', function ($folder = null) {
    try {
        $s3 = new S3Client([
            'version' => 'latest',
            'region' => config('filesystems.disks.s3.region'),
            'credentials' => [
                'key' => config('filesystems.disks.s3.key'),
                'secret' => config('filesystems.disks.s3.secret'),
            ],
        ]);

        // Prepare the prefix (e.g., '02/subfolder/' or '' for root)
        $prefix = $folder ? rtrim($folder, '/').'/' : '';

        $prefix = $prefix.'1032365030/1032365030-JSE933/';
        $items = ['files' => [], 'folders' => []];
        $continuationToken = null;

        do {
            $result = $s3->listObjectsV2([
                'Bucket' => config('filesystems.disks.s3.bucket'),
                'Prefix' => $prefix,
                'Delimiter' => '/',
                'ContinuationToken' => $continuationToken,
            ]);

            // Collect files, removing the prefix from each file name
            $files = collect($result['Contents'] ?? [])
                ->map(function ($file) use ($prefix, $s3) {
                    $key = $file['Key'];
                    $fileName = str_replace($prefix, '', $key);
                    // Generate a presigned URL for the file (valid for 1 hour)
                    $cmd = $s3->getCommand('GetObject', [
                        'Bucket' => config('filesystems.disks.s3.bucket'),
                        'Key' => $key,
                    ]);
                    $presignedUrl = $s3->createPresignedRequest($cmd, '+1 hour')->getUri()->__toString();

                    return [
                        'name' => $fileName,
                        'url' => $presignedUrl,
                    ];
                })
                ->all();
            $items['files'] = array_merge($items['files'], $files);

            // Collect subfolders, removing the prefix from each folder name
            $folders = collect($result['CommonPrefixes'] ?? [])
                ->pluck('Prefix')
                ->map(function ($folder) use ($prefix) {
                    return str_replace($prefix, '', $folder);
                })
                ->all();
            $items['folders'] = array_merge($items['folders'], $folders);

            $continuationToken = $result['NextContinuationToken'] ?? null;
        } while ($continuationToken);

        \Log::info('S3 Items:', [
            'files' => $items['files'],
            'folders' => $items['folders'],
            'bucket' => config('filesystems.disks.s3.bucket'),
            'prefix' => $prefix,
        ]);

        if (empty($items['files']) && empty($items['folders'])) {
            return response()->json(['message' => "No files or folders found in prefix: $prefix", 'items' => $items]);
        }

        return response()->json(['prefix' => $prefix, 'items' => $items]);
    } catch (\Exception $e) {
        \Log::error('S3 Listing Error: '.$e->getMessage());

        return response()->json(['error' => 'Error: '.$e->getMessage()], 500);
    }
});

Route::get('/s3', function () {
    try {
        $files = Storage::disk('s3')->files();
        \Log::info('S3 Files:', $files); // Log the files

        return response()->json($files);
    } catch (\Exception $e) {
        \Log::error('S3 Error: '.$e->getMessage()); // Log the error

        return response()->json(['error' => 'Error: '.$e->getMessage()], 500);
    }
});

Route::get('/s2', function () {
    try {
        // List files from the root
        $folder = ''; // Empty string for root
        // Use files() for root-level files only, or allFiles() for recursive listing
        $files = Storage::disk('s3')->files($folder); // Non-recursive (root only)
        // $files = Storage::disk('s3')->allFiles($folder); // Recursive (root + subfolders)

        \Log::info('S3 Files Listed:', [
            'files' => $files,
            'bucket' => config('filesystems.disks.s3.bucket'),
            'folder' => $folder,
        ]);

        if (empty($files)) {
            return response()->json(['message' => 'No files found in bucket root', 'files' => []]);
        }

        return response()->json($files);
    } catch (\Exception $e) {
        \Log::error('S3 Listing Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

        return response()->json(['error' => 'Error: '.$e->getMessage()], 500);
    }
});

Route::get('/ftp', function () {
    // Obtener datos necesarios del request
    $folderPath = 'Nueva carpeta';
    $modelId = '9e4c79f4-94bc-4463-bf9f-1834d9ce5caa';
    $company_id = '23a0eb68-95b6-49c0-9ad3-0f60627bf220';
    $company = Company::find($company_id);
    $modelType = 'Filing';

    if (! $folderPath) {
        return ['code' => 400, 'message' => 'Debe proporcionar una ruta de carpeta'];
    }

    // Construir la ruta completa en el directorio public
    $fullPath = public_path($folderPath);
    if (! is_dir($fullPath)) {
        return ['code' => 400, 'message' => 'La ruta especificada no es un directorio válido'];
    }

    // 2. Leer todos los nombres de archivos de la carpeta
    $files = scandir($fullPath);
    $fileList = array_filter($files, fn ($file) => ! in_array($file, ['.', '..']));
    if (empty($fileList)) {
        return ['code' => 400, 'message' => 'No se encontraron archivos en la carpeta'];
    }

    // Resolver el modelo
    $modelClass = 'App\\Models\\'.$modelType;
    if (! class_exists($modelClass)) {
        return ['code' => 400, 'message' => 'Modelo no válido'];
    }
    $modelInstance = $modelClass::find($modelId);
    $modelInstance->load(['filingInvoice']);
    if (! $modelInstance) {
        return ['code' => 404, 'message' => 'Instancia no encontrada'];
    }

    // Obtener datos para validación
    // $supportTypes = $this->supportTypeRepository->all();
    $supportTypes = SupportType::all();
    $validSupportCodes = $supportTypes->pluck('code')->toArray();
    $validInvoiceNumbers = $modelInstance->filingInvoice->pluck('invoice_number')->toArray();
    $companyNit = $company->nit;
    $uploadId = uniqid();
    $fileCount = count($fileList);

    // 3 y 4. Validar nombres de archivo y recolectar errores
    $errors = [];
    $validFiles = [];
    $seenConsecutives = [];

    foreach ($fileList as $index => $fileName) {
        $fullFilePath = $fullPath.'/'.$fileName;
        if (! is_file($fullFilePath)) {
            continue; // Saltar si no es un archivo
        }

        $parts = explode('.', $fileName);
        $nameWithoutExt = $parts[0];
        $extension = $parts[1] ?? '';
        $fileParts = explode('_', $nameWithoutExt);
        [$nit, $numFac, $codeSupport, $consecutive] = array_pad($fileParts, 4, null);

        // Validaciones
        if (count($fileParts) !== 4 || ! $extension) {
            $errors[] = [
                'fileName' => $fileName,
                'message' => 'Formato inválido. Debe ser NIT_NUMFAC_CODESUPPORT_CONSECUTIVE.EXT',
            ];

            continue;
        }

        if ($nit !== $companyNit) {
            $errors[] = [
                'fileName' => $fileName,
                'message' => "El NIT ({$nit}) no coincide con el de la compañía ({$companyNit})",
            ];

            continue;
        }

        if (! in_array($numFac, $validInvoiceNumbers)) {
            $errors[] = [
                'fileName' => $fileName,
                'message' => "El número de factura ({$numFac}) no es válido",
            ];

            continue;
        }

        if (! in_array($codeSupport, $validSupportCodes)) {
            $errors[] = [
                'fileName' => $fileName,
                'message' => "El código de soporte ({$codeSupport}) no es válido",
            ];

            continue;
        }

        if (! ctype_digit($consecutive)) {
            $errors[] = [
                'fileName' => $fileName,
                'message' => "El consecutivo ({$consecutive}) debe ser un valor numérico",
            ];

            continue;
        }

        $key = "{$nit}_{$numFac}_{$codeSupport}_{$consecutive}";
        if (in_array($key, $seenConsecutives)) {
            $errors[] = [
                'fileName' => $fileName,
                'message' => "El consecutivo ({$consecutive}) está duplicado para {$nit}_{$numFac}_{$codeSupport}",
            ];

            continue;
        }

        $seenConsecutives[] = $key;

        // Si pasa todas las validaciones, preparar para procesamiento
        $validFiles[] = [
            'path' => $fullFilePath,
            'name' => $fileName,
            'index' => $index,
            'nit' => $nit,
            'numFac' => $numFac,
            'codeSupport' => $codeSupport,
            'consecutive' => $consecutive,
        ];
    }

    // 5. Procesar solo los archivos válidos
    foreach ($validFiles as $fileData) {
        $invoice = $modelInstance->filingInvoice()->where('invoice_number', $fileData['numFac'])->first();
        $supportType = $supportTypes->where('code', $fileData['codeSupport'])->first();

        $supportName = str_replace(' ', '_', strtoupper($fileData['codeSupport']));
        $finalName = "{$fileData['nit']}_{$fileData['numFac']}_{$supportName}_{$fileData['consecutive']}";
        $finalPath = "companies/company_{$company_id}/filings/{$modelInstance->type->value}/filing_{$modelId}/invoices/{$fileData['numFac']}/supports/{$finalName}";

        $data = [
            'company_id' => $company_id,
            'fileable_type' => 'App\\Models\\FilingInvoice',
            'fileable_id' => $invoice->id,
            'support_type_id' => $supportType->id,
            // 'channel' => "filing.{$modelId}",
        ];

        ProcessMassUpload::dispatch(
            $fileData['path'],
            $fileData['name'],
            $uploadId,
            $fileData['index'] + 1,
            $fileCount,
            $finalPath,
            $data
        );

        // FilingInvoiceRowUpdated::dispatch($invoice->id);
    }

    // Respuesta final
    $response = [
        'code' => 200,
        'message' => 'Se procesaron '.count($validFiles)." de {$fileCount} archivos",
        'upload_id' => $uploadId,
        'count' => count($validFiles),
        'errors' => $errors,
    ];

    if (! empty($errors)) {
        $response['code'] = 202; // Indica que hubo éxito parcial
        $response['message'] .= '. Algunos archivos no se procesaron debido a errores.';
    }

    return $response;
});

// Route::get('/conciliation/uploadFile', [ConciliationController::class, 'uploadFile']);
