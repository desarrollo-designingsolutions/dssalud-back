<?php

namespace App\Helpers\Common;

use Illuminate\Support\Facades\Redis;

class ErrorCollector
{
    private static $errors = [];

    /**
     * Agrega un error al array de errores con la nueva estructura.
     *
     * @param  string  $validation  Código de la validación (ej. RVC001)
     * @param  string  $validationType  Tipo de validación (ej. R)
     * @param  string|null  $numInvoice  Número de factura o identificador padre
     * @param  string|null  $file  Nombre del archivo
     * @param  int|null  $row  Fila donde ocurrió el error
     * @param  string|null  $column  Columna específica (si aplica)
     * @param  mixed  $data  Datos que causaron el error
     * @param  string  $message  Mensaje con descripción y solución
     */
    public static function addError(
        string $keyRedis,
        string $validation,
        string $validationType,
        ?string $numInvoice,
        ?string $file,
        ?int $row,
        ?string $column,
        $data,
        string $message
    ): void {

        $errors = json_decode(Redis::get($keyRedis), 1);
        $errors[] = [
            'validacion' => $validation,
            'validacion_type_Y' => $validationType,
            'num_invoice' => $numInvoice,
            'file' => $file,
            'row' => $row,
            'column' => $column,
            'data' => $data,
            'error' => $message,
        ];

        Redis::set($keyRedis, json_encode($errors));
    }

    /**
     * Devuelve todos los errores recolectados.
     */
    public static function getErrors($keyRedis): array
    {

        $errors = json_decode(Redis::get($keyRedis), 1);

        return $errors;
    }

    /**
     * Limpia el array de errores.
     */
    public static function clear($keyRedis): void
    {
        Redis::set($keyRedis, json_encode([]));
    }
}
