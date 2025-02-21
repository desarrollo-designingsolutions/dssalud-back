<?php

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Redis;

function getPaginatedDataRedis(Request $request, $invoiceId)
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
