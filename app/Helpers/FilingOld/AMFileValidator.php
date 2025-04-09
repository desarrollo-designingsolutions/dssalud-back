<?php

namespace App\Helpers\FilingOld;

use App\Helpers\Common\ErrorCollector;

class AMFileValidator
{
    /**
     * Valida el archivo AM y sus columnas.
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
            'columna 6: Código del medicamento',
            'columna 7: Tipo de medicamento',
            'columna 8: Nombre genérico del medicamento',
            'columna 9: Forma farmacéutica',
            'columna 10: Concentración del medicamento',
            'columna 11: Unidad de medida del medicamento',
            'columna 12: Número de unidades',
            'columna 13: Valor unitario de medicamento',
            'columna 14: Valor total de medicamento',
        ];

        // 1. Número de la factura (columna 0)
        // Valor obligatorio
        if (empty($rowData[0])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AM_ERROR_001',
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
                'FILE_AM_ERROR_002',
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
                'FILE_AM_ERROR_003',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[2],
                $rowData[2],
                'El dato ingresado no es permitido'
            );
        }

        // 4. Tipo de medicamento (columna 7)
        // Valor obligatorio
        if (empty($rowData[6])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AM_ERROR_004',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[7],
                $rowData[6],
                'El dato registrado es obligatorio.'
            );
        }

        // Unicamente los valores permitidos
        $allowedPrefixes = ['1', '2'];
        if (! in_array($rowData[6], $allowedPrefixes)) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AM_ERROR_005',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[7],
                $rowData[6],
                'El dato ingresado no es permitido'
            );
        }

        // 5. Nombre genérico del medicamento (columna 8)
        // Valor obligatorio
        if (empty($rowData[8])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AM_ERROR_006',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[8],
                $rowData[8],
                'La Hora de ingreso del usuario a observacion es un dato obligatorio.'
            );
        }

        // 6. Forma farmacéutica (columna 9)
        // Valor obligatorio
        if (empty($rowData[9])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AM_ERROR_007',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[9],
                $rowData[9],
                'El campo causa externa es un dato de registro obligatorio.'
            );
        }

        // 7. Concentración del medicamento (columna 10)
        // Valor obligatorio
        if (empty($rowData[10])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AM_ERROR_008',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[10],
                $rowData[10],
                'El dato registrado es obligatorio.'
            );
        }

        // 8. Unidad de medida del medicamento (columna 11)
        // Valor obligatorio
        if (empty($rowData[11])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AM_ERROR_009',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[11],
                $rowData[11],
                'El dato registrado es obligatorio.'
            );
        }

        // 8. Número de unidades (columna 12)
        // Valor obligatorio
        if (empty($rowData[12])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AM_ERROR_010',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[12],
                $rowData[12],
                'El dato registrado es obligatorio.'
            );
        }

        // 8. Valor total de medicamento (columna 13)
        // Valor obligatorio
        if (empty($rowData[13])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AM_ERROR_011',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[13],
                $rowData[13],
                'El dato registrado es obligatorio.'
            );
        }

        // logMessage(ErrorCollector::getErrors($keyErrorRedis));
    }
}
