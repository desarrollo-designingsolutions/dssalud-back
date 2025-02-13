<?php

namespace App\Traits;

use App\Helpers\Constants;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Throwable;

trait HttpTrait
{
    /**
     * Ejecuta una operación dentro de una transacción de base de datos.
     *
     * @param callable $callback
     * @return JsonResponse
     */
    public function runTransaction(callable $callback, $responseStatus = 200, bool $debug = false): JsonResponse|null
    {
        if ($debug) {
            DB::beginTransaction();
            $result = $callback();
            DB::rollBack(); // Revierte cambios después de obtener $result

            return response()->json([
                'debug' => $result,
                'message' => 'Rollback automático en debug'
            ], $responseStatus);
        }

        DB::beginTransaction();
        try {


            $result = $callback();
            DB::commit();

            if (!$result) {
                return null;
            }

            return response()->json($result, $responseStatus);
        } catch (Throwable $th) {
            DB::rollBack();

            // Intentar decodificar el mensaje de error como JSON
            $errorData = json_decode($th->getMessage(), true);

            // Verificar si contiene 'message' y si es true
            if (isset($errorData['message']) && !empty($errorData['message'])) {
                $errorMessage = $errorData['message'] ?? Constants::ERROR_MESSAGE_TRYCATCH;
            } else {
                $errorMessage = Constants::ERROR_MESSAGE_TRYCATCH;
            }

            if (env("APP_DEBUG")) {
                return response()->json([
                    'code' => 500,
                    'message' => $errorMessage,
                    'error' => $th->getMessage(),
                    'line' => $th->getLine(),
                ], 500);
            }

            return response()->json([
                'code' => 500,
                'message' => $errorMessage,
            ], 500);
        }
    }

    /**
     * Ejecuta una operación sin transacción.
     *
     * @param callable $callback
     * @return JsonResponse
     */
    public function execute(callable $callback, $responseStatus = 200): JsonResponse|null
    {
        try {
            $result = $callback();

            if (!$result) {
                return null;
            }

            return response()->json($result, $responseStatus);
        } catch (Throwable $th) {
            // Intentar decodificar el mensaje de error como JSON
            $errorData = json_decode($th->getMessage(), true);

            // Verificar si contiene 'message' y si es true
            if (isset($errorData['message']) && !empty($errorData['message'])) {
                $errorMessage = $errorData['message'] ?? Constants::ERROR_MESSAGE_TRYCATCH;
            } else {
                $errorMessage = Constants::ERROR_MESSAGE_TRYCATCH;
            }

            if (env("APP_DEBUG")) {
                return response()->json([
                    'code' => 500,
                    'message' => $errorMessage,
                    'error' => $th->getMessage(),
                    'line' => $th->getLine(),
                ], 500);
            }

            return response()->json([
                'code' => 500,
                'message' => $errorMessage,
            ], 500);
        }
    }
}
