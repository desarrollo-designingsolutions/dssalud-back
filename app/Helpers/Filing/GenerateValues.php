<?php

use App\Enums\Filing\StatusFilingEnum;
use App\Enums\Filing\StatusFillingInvoiceEnum;
use App\Models\Filing;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Redis;

function getPaginatedDataRedisold(Request $request, $invoiceId)
{
    // Parámetros de paginación y ordenamiento
    $perPage = $request->input('per_page', 10);
    $page = $request->input('page', 1);
    $sortBy = $request->input('sortBy'); // Campo por el que ordenar
    $sortDesc = $request->input('sortDesc'); // Dirección (true = descendente, false = ascendente)

    $redisKey = "invoice:{$invoiceId}:users";
    Redis::select(0);
    $total = Redis::llen($redisKey) ?? 0;

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
            ]
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
            $comparison = strcmp($valueA, $valueB); // Comparación de strings
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
        ]
    ];
}


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
        if (!$invoice) {
            return [
                'data' => [],
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                    'from' => 0,
                    'to' => 0,
                ]
            ];
        }
        logMessage("no deberia entrar aqui");

        // Guardar el elemnto de la factura en Redis
        $redisKeyInvoice = "filingInvoice:{$invoice->id}:dataBd";
        Redis::set($redisKeyInvoice, json_encode($invoice));
        Redis::expire($redisKeyInvoice, 2592000); // 30 días en segundos (60 * 60 * 24 * 30)


        $jsonPath = storage_path('app/public/' . $invoice->path_json);
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
                ]
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
            ]
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
        ]
    ];
}

function validateFilingStatus($filing_id)
{
    $filing = Filing::find($filing_id);

    if (!$filing) {
        // Manejar el caso en que no se encuentra el filing
        return;
    }

    $invoices = $filing->filingInvoice;

    // Verificar si todas las facturas están en status 'prefiling' y status_xml 'no validated'
    $allPrefilingAndNoValidated = $invoices->every(function ($invoice) {
        return $invoice->status === StatusFillingInvoiceEnum::PRE_FILING && $invoice->status_xml === StatusFillingInvoiceEnum::NOT_VALIDATED;
    });

    // Verificar si al menos una factura está en status 'prefiling' o status_xml 'no validated'
    $anyPrefilingOrNoValidated = $invoices->contains(function ($invoice) {
        return $invoice->status === StatusFillingInvoiceEnum::PRE_FILING || $invoice->status_xml === StatusFillingInvoiceEnum::NOT_VALIDATED;
    });

    // Verificar si todas las facturas están en status 'filing' y al menos una tiene status_xml 'no validated'
    $allFilingAndAnyNoValidated = $invoices->every(function ($invoice) {
        return $invoice->status === StatusFillingInvoiceEnum::FILING;
    }) && $invoices->contains(function ($invoice) {
        return $invoice->status_xml === StatusFillingInvoiceEnum::NOT_VALIDATED;
    });

    // Verificar si al menos una factura está en status 'prefiling' y todas tienen status_xml 'validated'
    $anyPrefilingAndAllValidated = $invoices->contains(function ($invoice) {
        return $invoice->status === StatusFillingInvoiceEnum::PRE_FILING;
    }) && $invoices->every(function ($invoice) {
        return $invoice->status_xml === StatusFillingInvoiceEnum::VALIDATED;
    });

    // Verificar si todas las facturas están en status 'filing' y todas tienen status_xml 'validated'
    $allFilingAndAllValidated = $invoices->every(function ($invoice) {
        return $invoice->status === StatusFillingInvoiceEnum::FILING && $invoice->status_xml === StatusFillingInvoiceEnum::VALIDATED;
    });

    if ($allPrefilingAndNoValidated || $anyPrefilingOrNoValidated || $allFilingAndAnyNoValidated || $anyPrefilingAndAllValidated) {
        $filing->status = StatusFilingEnum::INCOMPLETE;
    } elseif ($allFilingAndAllValidated) {
        $filing->status = StatusFilingEnum::COMPLETED;
    }

    $filing->save();
}
