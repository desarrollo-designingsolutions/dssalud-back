<?php

namespace App\Traits;

use App\Helpers\Constants;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

trait HttpResponseTrait
{
    /**
     * Maneja errores y devuelve una respuesta JSON estandarizada.
     *
     * @param  Throwable  $th  La excepción capturada
     * @param  int  $errorStatus  Código de estado HTTP para el error
     * @param  bool  $debug  Si es true, incluye detalles adicionales del error
     */
    private function handleError(Throwable $th, int $errorStatus = 500, bool $debug = false): JsonResponse
    {
        $errorData = json_decode($th->getMessage(), true);
        $errorMessage = (isset($errorData['message']) && ! empty($errorData['message']))
            ? $errorData['message']
            : Constants::ERROR_MESSAGE_TRYCATCH;

        $response = [
            'code' => $errorStatus,
            'message' => $errorMessage,
            'error' => $th->getMessage(),
            'line' => $th->getLine(),
        ];

        if ($debug) {
            $response['debug_mode'] = true;
            $response['debug_message'] = 'Modo de depuración activado: se capturó una excepción.';
        }

        return response()->json($response, $errorStatus);
    }

    /**
     * Ejecuta una operación dentro de una transacción de base de datos y devuelve una respuesta.
     *
     * @param  callable  $callback  La lógica a ejecutar dentro de la transacción
     * @param  int  $successStatus  Código de estado HTTP para respuestas exitosas
     * @param  int  $errorStatus  Código de estado HTTP para errores
     * @param  bool  $allowNull  Si es true, permite devolver null como respuesta válida
     * @param  bool  $debug  Si es true, incluye detalles adicionales en caso de error
     * @param  bool  $rawResponse  Si es true, devuelve el resultado sin envolver en JsonResponse
     * @return JsonResponse|mixed|null Respuesta JSON, resultado crudo o null según configuración
     *
     * @throws InvalidArgumentException Si el callback no es ejecutable
     * @throws Throwable Si ocurre un error durante la ejecución
     */
    public function runTransaction(
        callable $callback,
        int $successStatus = 200,
        int $errorStatus = 500,
        bool $allowNull = false,
        bool $debug = false,
        bool $rawResponse = false
    ) {
        if (! is_callable($callback)) {
            throw new InvalidArgumentException('El parámetro $callback debe ser una función ejecutable.');
        }

        DB::beginTransaction();
        try {
            $result = $callback();

            // Si debug es true, no hacemos commit y devolvemos el resultado con un mensaje de depuración
            if ($debug) {
                DB::rollBack(); // Revierte cambios después de obtener $result

                $response = [
                    'debug' => true,
                    'message' => 'Modo de depuración activado: rollback automático realizado.',
                    'data' => $result, // Incluimos el resultado del callback
                ];

                return response()->json($response, $successStatus);
            }

            DB::commit();

            if (! $result && ! $allowNull) {
                return null;
            }

            return $rawResponse ? $result : response()->json($result, $successStatus);
        } catch (QueryException $qe) {
            DB::rollBack();

            return $this->handleError($qe, $errorStatus, $debug);
        } catch (Throwable $th) {
            DB::rollBack();

            return $this->handleError($th, $errorStatus, $debug);
        }
    }

    /**
     * Ejecuta una operación sin transacción y devuelve una respuesta.
     *
     * @param  callable  $callback  La lógica a ejecutar
     * @param  int  $successStatus  Código de estado HTTP para respuestas exitosas
     * @param  int  $errorStatus  Código de estado HTTP para errores
     * @param  bool  $allowNull  Si es true, permite devolver null como respuesta válida
     * @param  bool  $debug  Si es true, incluye detalles adicionales en caso de error
     * @param  bool  $rawResponse  Si es true, devuelve el resultado sin envolver en JsonResponse
     * @return JsonResponse|mixed|null Respuesta JSON, resultado crudo o null según configuración
     *
     * @throws InvalidArgumentException Si el callback no es ejecutable
     * @throws Throwable Si ocurre un error durante la ejecución
     */
    public function execute(
        callable $callback,
        int $successStatus = 200,
        int $errorStatus = 500,
        bool $allowNull = false,
        bool $debug = false,
        bool $rawResponse = false
    ) {
        if (! is_callable($callback)) {
            throw new InvalidArgumentException('El parámetro $callback debe ser una función ejecutable.');
        }

        try {
            $result = $callback();

            if (! $result && ! $allowNull) {
                return null;
            }

            return $rawResponse ? $result : response()->json($result, $successStatus);
        } catch (QueryException $qe) {
            return $this->handleError($qe, $errorStatus, $debug);
        } catch (Throwable $th) {
            return $this->handleError($th, $errorStatus, $debug);
        }
    }
}
