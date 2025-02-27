<?php

use App\Jobs\File\ProcessMassUpload;
use App\Models\Company;
use App\Models\FilingInvoice;
use App\Models\SupportType;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/ftp', function () {
    // Obtener datos necesarios del request
    $folderPath = "Nueva carpeta";
    $modelId = "9e4c79f4-94bc-4463-bf9f-1834d9ce5caa";
    $company_id = "23a0eb68-95b6-49c0-9ad3-0f60627bf220";
    $company = Company::find($company_id);
    $modelType = "Filing";



    if (!$folderPath) {
        return ['code' => 400, 'message' => 'Debe proporcionar una ruta de carpeta'];
    }

    // Construir la ruta completa en el directorio public
    $fullPath = public_path($folderPath);
    if (!is_dir($fullPath)) {
        return ['code' => 400, 'message' => 'La ruta especificada no es un directorio válido'];
    }

    // 2. Leer todos los nombres de archivos de la carpeta
    $files = scandir($fullPath);
    $fileList = array_filter($files, fn($file) => !in_array($file, ['.', '..']));
    if (empty($fileList)) {
        return ['code' => 400, 'message' => 'No se encontraron archivos en la carpeta'];
    }


    // Resolver el modelo
    $modelClass = 'App\\Models\\' . $modelType;
    if (!class_exists($modelClass)) {
        return ['code' => 400, 'message' => 'Modelo no válido'];
    }
    $modelInstance = $modelClass::find($modelId);
    $modelInstance->load(["filingInvoice"]);
    if (!$modelInstance) {
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
        $fullFilePath = $fullPath . '/' . $fileName;
        if (!is_file($fullFilePath)) {
            continue; // Saltar si no es un archivo
        }

        $parts = explode('.', $fileName);
        $nameWithoutExt = $parts[0];
        $extension = $parts[1] ?? '';
        $fileParts = explode('_', $nameWithoutExt);
        [$nit, $numFac, $codeSupport, $consecutive] = array_pad($fileParts, 4, null);

        // Validaciones
        if (count($fileParts) !== 4 || !$extension) {
            $errors[] = [
                'fileName' => $fileName,
                'message' => 'Formato inválido. Debe ser NIT_NUMFAC_CODESUPPORT_CONSECUTIVE.EXT'
            ];
            continue;
        }

        if ($nit !== $companyNit) {
            $errors[] = [
                'fileName' => $fileName,
                'message' => "El NIT ({$nit}) no coincide con el de la compañía ({$companyNit})"
            ];
            continue;
        }

        if (!in_array($numFac, $validInvoiceNumbers)) {
            $errors[] = [
                'fileName' => $fileName,
                'message' => "El número de factura ({$numFac}) no es válido"
            ];
            continue;
        }

        if (!in_array($codeSupport, $validSupportCodes)) {
            $errors[] = [
                'fileName' => $fileName,
                'message' => "El código de soporte ({$codeSupport}) no es válido"
            ];
            continue;
        }

        if (!ctype_digit($consecutive)) {
            $errors[] = [
                'fileName' => $fileName,
                'message' => "El consecutivo ({$consecutive}) debe ser un valor numérico"
            ];
            continue;
        }

        $key = "{$nit}_{$numFac}_{$codeSupport}_{$consecutive}";
        if (in_array($key, $seenConsecutives)) {
            $errors[] = [
                'fileName' => $fileName,
                'message' => "El consecutivo ({$consecutive}) está duplicado para {$nit}_{$numFac}_{$codeSupport}"
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
            'consecutive' => $consecutive
        ];
    }

    // 5. Procesar solo los archivos válidos
    foreach ($validFiles as $fileData) {
        $invoice = $modelInstance->filingInvoice()->where("invoice_number", $fileData['numFac'])->first();
        $supportType = $supportTypes->where("code", $fileData['codeSupport'])->first();

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
        'message' => "Se procesaron " . count($validFiles) . " de {$fileCount} archivos",
        'upload_id' => $uploadId,
        'count' => count($validFiles),
        'errors' => $errors
    ];

    if (!empty($errors)) {
        $response['code'] = 202; // Indica que hubo éxito parcial
        $response['message'] .= ". Algunos archivos no se procesaron debido a errores.";
    }

    return $response;
});

