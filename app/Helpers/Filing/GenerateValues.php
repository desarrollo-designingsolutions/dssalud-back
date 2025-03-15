<?php

use App\Enums\Filing\StatusFilingEnum;
use App\Enums\Filing\StatusFilingInvoiceEnum;
use App\Models\Filing;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Redis;

function getPaginatedDataRedis(Request $request, $invoiceId, $model, $redisPrefix = 'filingInvoice')
{
    // Parámetros de paginación y ordenamiento
    $perPage = $request->input('per_page', 10);
    $page = $request->input('page', 1);
    $sortBy = $request->input('sortBy');
    $sortDesc = $request->input('sortDesc');

    // Construir la llave de Redis
    $redisKey = "laravel_database_{$redisPrefix}:{$invoiceId}:users";
    Redis::select(0);
    $total = Redis::llen($redisKey) ?? 0;

    // Regenerar si no hay datos en Redis
    if ($total === 0) {
        $invoice = $model->find($invoiceId);
        if (! $invoice) {
            return [
                'data' => [],
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                    'from' => 0,
                    'to' => 0,
                ],
            ];
        }
        // logMessage('no deberia entrar aqui');

        // Guardar el elemnto de la factura en Redis
        $redisKeyInvoice = "filingInvoice:{$invoice->id}:dataBd";
        Redis::set($redisKeyInvoice, json_encode($invoice));
        Redis::expire($redisKeyInvoice, 2592000); // 30 días en segundos (60 * 60 * 24 * 30)

        $jsonPath = storage_path('app/public/'.$invoice->path_json);
        if (file_exists($jsonPath)) {
            $jsonContent = file_get_contents($jsonPath);
            $data = json_decode($jsonContent, true);
            $users = $data['usuarios'] ?? [];

            // Repoblar Redis
            foreach ($users as $user) {
                Redis::rpush($redisKey, json_encode($user));
            }
            Redis::expire($redisKey, 2592000); // 1 mes de TTL
            $total = count($users);
        } else {
            return [
                'data' => [],
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                    'from' => 0,
                    'to' => 0,
                ],
            ];
        }
    }

    if ($total === 0) {
        return [
            'data' => [],
            'pagination' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $perPage,
                'total' => 0,
                'from' => 0,
                'to' => 0,
            ],
        ];
    }

    // Calcular índices de inicio y fin
    $start = ($page - 1) * $perPage;
    $end = $start + $perPage - 1;

    // Obtener los usuarios del rango
    $users = Redis::lrange($redisKey, $start, $end);
    $users = array_map('json_decode', $users, array_fill(0, count($users), true));

    // Ordenar los usuarios si hay parámetros de ordenamiento
    if ($sortBy) {
        usort($users, function ($a, $b) use ($sortBy, $sortDesc) {
            $valueA = $a[$sortBy] ?? '';
            $valueB = $b[$sortBy] ?? '';
            $comparison = strcmp($valueA, $valueB);

            return $sortDesc ? -$comparison : $comparison;
        });
    }

    $paginator = new LengthAwarePaginator(
        $users,
        $total,
        $perPage,
        $page,
        ['path' => "/api/filing-invoices/{$invoiceId}/users"]
    );

    return [
        'data' => $paginator->items(),
        'pagination' => [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ],
    ];
}

function validateFilingStatus($filing_id)
{
    $filing = Filing::find($filing_id);

    if (! $filing) {
        // Manejar el caso en que no se encuentra el filing
        return;
    }

    $invoices = $filing->filingInvoice;

    // Verificar si todas las facturas están en status 'prefiling' y status_xml 'no validated'
    $allPrefilingAndNoValidated = $invoices->every(function ($invoice) {
        return $invoice->status === StatusFilingInvoiceEnum::FILINGINVOICE_EST_001 && $invoice->status_xml === StatusFilingInvoiceEnum::FILINGINVOICE_EST_004;
    });

    // Verificar si al menos una factura está en status 'prefiling' o status_xml 'no validated'
    $anyPrefilingOrNoValidated = $invoices->contains(function ($invoice) {
        return $invoice->status === StatusFilingInvoiceEnum::FILINGINVOICE_EST_001 || $invoice->status_xml === StatusFilingInvoiceEnum::FILINGINVOICE_EST_004;
    });

    // Verificar si todas las facturas están en status 'filing' y al menos una tiene status_xml 'no validated'
    $allFilingAndAnyNoValidated = $invoices->every(function ($invoice) {
        return $invoice->status === StatusFilingInvoiceEnum::FILINGINVOICE_EST_002;
    }) && $invoices->contains(function ($invoice) {
        return $invoice->status_xml === StatusFilingInvoiceEnum::FILINGINVOICE_EST_004;
    });

    // Verificar si al menos una factura está en status 'prefiling' y todas tienen status_xml 'validated'
    $anyPrefilingAndAllValidated = $invoices->contains(function ($invoice) {
        return $invoice->status === StatusFilingInvoiceEnum::FILINGINVOICE_EST_001;
    }) && $invoices->every(function ($invoice) {
        return $invoice->status_xml === StatusFilingInvoiceEnum::FILINGINVOICE_EST_003;
    });

    // Verificar si todas las facturas están en status 'filing' y todas tienen status_xml 'validated'
    $allFilingAndAllValidated = $invoices->every(function ($invoice) {
        return $invoice->status === StatusFilingInvoiceEnum::FILINGINVOICE_EST_002 && $invoice->status_xml === StatusFilingInvoiceEnum::FILINGINVOICE_EST_003;
    });

    if ($allPrefilingAndNoValidated || $anyPrefilingOrNoValidated || $allFilingAndAnyNoValidated || $anyPrefilingAndAllValidated) {
        $filing->status = StatusFilingEnum::FILING_EST_004;
    } elseif ($allFilingAndAllValidated) {
        $filing->status = StatusFilingEnum::FILING_EST_005;
    }

    $filing->save();
}

function filingOld_deletefileZipData($data)
{
    // eliminamos el archivo zip subido
    $fileDelete = env('SYSTEM_URL_BACK').$data->path_zip;

    $fileDelete = public_path($fileDelete);

    if (file_exists($fileDelete)) {
        unlink($fileDelete);
    }

    $data->path_zip = null;
    $data->save();
}

function filingOld_openFileZip($fileZip)
{
    $fileZip = public_path('storage/'.$fileZip);

    $zip = new ZipArchive;
    if ($zip->open($fileZip) === true) {

        // Directorio temporal para extraer los archivos del ZIP
        $tempDirectory = storage_path('app/temp_zip');
        if (! is_dir($tempDirectory)) {
            mkdir($tempDirectory, 0777, true);
        }

        $archivos = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $contenido = $zip->getFromName($filename);

            $rutaTemporal = $tempDirectory.'/'.$filename;
            // Extraer el archivo del ZIP
            $zip->extractTo($tempDirectory, $filename);

            if ($extension == 'txt') {
                // Verificar y convertir la codificación a UTF-8 si es necesario
                if (! mb_check_encoding($contenido, 'UTF-8')) {
                    // Proporciona la codificación de caracteres de origen si la conoces (por ejemplo, ISO-8859-1).
                    $contenido = mb_convert_encoding($contenido, 'UTF-8', 'ISO-8859-1');
                }
            }

            $archivos[] = [
                'name' => $filename,
                'extension' => $extension,
                'content' => $contenido,
                'rutaTemporal' => $rutaTemporal,
            ];
        }
        $zip->close();

        return $archivos;
    } else {
        return false; // O manejar el error de apertura del archivo ZIP de la forma que desees.
    }
}

function filingOld_buildAllDataTogether($files)
{
    $dataArrayAF = [];
    $dataArrayAC = [];
    $dataArrayUS = [];
    $dataArrayAP = [];
    $dataArrayAM = [];
    $dataArrayAU = [];
    $dataArrayAH = [];
    $dataArrayAN = [];
    $dataArrayAT = [];
    $dataArrayCT = [];

    // Define an array to map substrings to format functions
    $mapping = [
        'AF' => 'formatValueAF',
        'AC' => 'formatValueAC',
        'US' => 'formatValueUS',
        'AP' => 'formatValueAP',
        'AM' => 'formatValueAM',
        'AU' => 'formatValueAU',
        'AH' => 'formatValueAH',
        'AN' => 'formatValueAN',
        'AT' => 'formatValueAT',
        'CT' => 'formatValueCT',
    ];

    // Loop through the files
    foreach ($files as $key => $value) {
        // Check if the name contains one of the specified substrings
        foreach ($mapping as $substring => $formatFunction) {
            if (stripos($value['name'], $substring) !== false) {
                // Format the data and add numeration
                $dataArray = formatDataTxt($value['content'], $formatFunction);
                agregarNumeracion($dataArray, $value['name']);
            }
        }
    }

    $dataArrayAF = collect($dataArrayAF);
    $dataArrayAC = collect($dataArrayAC);
    $dataArrayUS = collect($dataArrayUS);
    $dataArrayAP = collect($dataArrayAP);
    $dataArrayAM = collect($dataArrayAM);
    $dataArrayAU = collect($dataArrayAU);
    $dataArrayAH = collect($dataArrayAH);
    $dataArrayAN = collect($dataArrayAN);
    $dataArrayAT = collect($dataArrayAT);
    $dataArrayCT = collect($dataArrayCT);

    $dataArrayAF = $dataArrayAF->map(function ($item) use ($dataArrayAC, $dataArrayUS, $dataArrayAP, $dataArrayAM, $dataArrayAU, $dataArrayAH, $dataArrayAN, $dataArrayAT) {

        filingOld_invoiceUserServices($dataArrayAC, $dataArrayUS, $item, 'consultas');

        filingOld_invoiceUserServices($dataArrayAP, $dataArrayUS, $item, 'procedimientos');

        filingOld_invoiceUserServices($dataArrayAM, $dataArrayUS, $item, 'medicamentos');

        filingOld_invoiceUserServices($dataArrayAU, $dataArrayUS, $item, 'urgencias');

        filingOld_invoiceUserServices($dataArrayAH, $dataArrayUS, $item, 'hospitalizacion');

        filingOld_invoiceUserServices($dataArrayAN, $dataArrayUS, $item, 'recienNacidos');

        filingOld_invoiceUserServices($dataArrayAT, $dataArrayUS, $item, 'otrosServicios');

        return $item;
    })->toArray();

    return [
        'data' => $dataArrayAF,
    ];
}

function filingOld_invoiceUserServices($dataArray, $dataArrayUS, &$invoice, $keyService)
{
    $registers = $dataArray->filter(function ($atItem) use ($invoice) {
        return $atItem['numFEVPagoModerador'] == $invoice['numFactura'];
    })->values();

    $i = 0;
    foreach ($registers as $key => $value) {
        // Agregar los elementos encontrados a la subcolección 'usuarios'

        $usuario = $dataArrayUS->filter(function ($acItem) use ($value) {
            return $acItem['numDocumentoIdentificacion'] == $value['numDocumentoIdentificacion'];
        })->first();

        $user = collect($invoice['usuarios'])->filter(function ($value) use ($usuario) {
            return $value['numDocumentoIdentificacion'] == $usuario['numDocumentoIdentificacion'];
        })->values();

        if (count($user) == 0) {
            $invoice['usuarios'][$i] = $usuario;
            $invoice['usuarios'][$i]['servicios'] = [];
        }

        if (isset($invoice['usuarios'][$i]['servicios']) && ! isset($invoice['usuarios'][$i]['servicios'][$keyService])) {
            $invoice['usuarios'][$i]['servicios'][$keyService] = [];
        }

        $dataService = $dataArray->filter(function ($atItem) use ($invoice, $usuario) {
            return $atItem['numFEVPagoModerador'] == $invoice['numFactura'] && $atItem['numDocumentoIdentificacion'] == $usuario['numDocumentoIdentificacion'];
        })->values();

        if (isset($invoice['usuarios'][$i]['servicios'][$keyService]) && count($invoice['usuarios'][$i]['servicios'][$keyService]) == 0) {
            $invoice['usuarios'][$i]['servicios'][$keyService] = $dataService;
        }

        $i++;
    }
}
