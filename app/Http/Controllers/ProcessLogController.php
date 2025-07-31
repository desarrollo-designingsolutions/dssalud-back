<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Services\ProcessBatchService;

class ProcessLogController extends Controller
{
    public function getErrors(string $batchId, Request $request)
    {
        // Validar parámetros
        $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:500',
            'error_type' => 'sometimes|string'
        ]);

        try {
            $page = $request->input('page', 1);
            $perPage = $request->input('per_page', 100);
            $errorType = $request->input('error_type');

            // Obtener errores paginados
             $result = ProcessBatchService::getErrors($batchId, $page, $perPage, $errorType);

            return response()->json([
                'success' => true,
                'data' => $result['data'],
                'meta' => $result['meta']
            ]);

        } catch (\Illuminate\Contracts\Filesystem\FileNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontraron errores para este proceso'
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar los errores: ' . $e->getMessage()
            ], 500);
        }
    }
}
