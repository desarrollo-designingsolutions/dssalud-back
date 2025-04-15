<?php

namespace App\Helpers\FilingOld;

use App\Helpers\Common\ErrorCollector;

class ATFileValidator
{
    /**
     * Valida el archivo AT y sus columnas.
     *
     * @param  string  $fileName  Nombre del archivo
     * @param  string  $rowData  datos de la fila del txt a validar
     * @param  string  $rowNumber  numero de la fila del txt a validar
     * @param  string  $filing_id  numero de la fila del txt a validar
     */
    public static function validate(string $fileName, string $rowData, $rowNumber, $filing_id): void
    {
        $keyErrorRedis = "filingOld:{$filing_id}:errors";

        $rowData = array_map('trim', explode(',', $rowData));

        $titleColumn = [
            'columna 1: Número de la factura',
            'columna 2: Código del prestador de servicios de salud',
            'columna 3: Tipo de identificación del usuario',
            'columna 4: Número de identificación del usuario en el sistema',
            'columna 5: Numero de autorizacion',
            'columna 6: Tipo de servicio',
            'columna 7: Codigo del servicio',
            'columna 8: Nombre del servicio',
            'columna 9: Cantidad',
            'columna 10: Valor unitario del material e insumo',
            'columna 11: Valor total del material e insumo',
        ];

        // 1. Número de la factura (columna 0)
        // Valor obligatorio
        if (empty($rowData[0])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AT_ERROR_001',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[0],
                $rowData[0],
                'El dato registrado es obligatorio.'
            );
        }

        // 2. Código del prestador de servicios de salud (columna 1)
        // Valor obligatorio
        if (empty($rowData[1])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AT_ERROR_002',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[1],
                $rowData[1],
                'El dato registrado es obligatorio.'
            );
        }

        // 3. Tipo de identificación del usuario (columna 3)
        // Unicamente los valores permitidos
        $allowedPrefixes = ['CC', 'CE', 'CD', 'PA', 'SC', 'PE', 'RE', 'RC', 'TI', 'CN', 'AS', 'MS', 'DE', 'PT', 'SI'];
        if (! in_array($rowData[2], $allowedPrefixes)) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AT_ERROR_003',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[2],
                $rowData[2],
                'El dato ingresado no es permitido'
            );
        }

        // 4. Tipo de servicio (columna 5)
        // Valor obligatorio
        if (empty($rowData[5])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AT_ERROR_004',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[5],
                $rowData[5],
                'El dato registrado es obligatorio.'
            );
        }

        // 5. Nombre del servicio (columna 7)
        // Valor obligatorio
        if (empty($rowData[7])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AT_ERROR_005',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[7],
                $rowData[7],
                'La Hora de ingreso del usuario a observacion es un dato obligatorio.'
            );
        }

        // logMessage(ErrorCollector::getErrors($keyErrorRedis));
    }
}
