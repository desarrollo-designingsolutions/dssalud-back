<?php

namespace App\Helpers\FilingOld;

use App\Helpers\Common\ErrorCollector;

class AHFileValidator
{
    /**
     * Valida el archivo AH y sus columnas.
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
            'columna 5: Via de ingreso a la institucion',
            'columna 6: Fecha de ingreso del usuario a la institución',
            'columna 7: Hora de ingreso del usuario a la Institución',
            'columna 8: Numero de autorizacion',
            'columna 9: Causa externa',
            'columna 10: Diagnostico principal de ingreso',
            'columna 11: Diagnóstico principal de egreso',
            'columna 12: Diagnóstico relacionado Nro. 1 de egreso',
            'columna 13: Diagnóstico relacionado Nro. 2 de egreso',
            'columna 14: Diagnóstico relacionado Nro. 3 de egreso',
            'columna 15: Diagnóstico de la complicacion',
            'columna 16: Estado a la salida',
            'columna 17: Diagnóstico de la causa básica de muerte',
            'columna 18: Fecha de egreso del usuario a la institución',
            'columna 19: Hora de egreso del usuario de la institución',
        ];

        // 1. Número de la factura (columna 0)
        // Valor obligatorio
        if (empty($rowData[0])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AP_ERROR_001',
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
                'FILE_AP_ERROR_002',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[1],
                $rowData[1],
                'El dato registrado es obligatorio.'
            );
        }

        // 3. Via de ingreso a la institucion (columna 4)
        // Valor obligatorio
        if (empty($rowData[4])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AP_ERROR_003',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[4],
                $rowData[4],
                'El dato registrado es obligatorio.'
            );
        }

        // Unicamente los valores permitidos
        $allowedPrefixes = ['1', '2', '3', '4'];
        if (! in_array($rowData[2], $allowedPrefixes)) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AP_ERROR_004',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[2],
                $rowData[2],
                'El dato ingresado no es permitido'
            );
        }

        // 4. Fecha de ingreso del usuario a la institución (columna 5)
        // Valor obligatorio
        if (empty($rowData[5])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AP_ERROR_005',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[5],
                $rowData[5],
                'La Fecha de ingreso del usuario a la institución es un dato obligatorio.'
            );
        }

        // 5. Hora de ingreso del usuario a la Institución (columna 6)
        // Valor obligatorio
        if (empty($rowData[6])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AP_ERROR_006',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[6],
                $rowData[6],
                'La Hora de ingreso del usuario a observacion es un dato obligatorio.'
            );
        }

        // 6. Causa externa (columna 7)
        // Valor obligatorio
        if (empty($rowData[7])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AP_ERROR_007',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[7],
                $rowData[7],
                'El campo causa externa es un dato de registro obligatorio.'
            );
        }

        // Unicamente los valores permitidos
        $allowedPrefixes = ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12', '13', '14', '15'];
        if (! in_array($rowData[2], $allowedPrefixes)) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AP_ERROR_008',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[2],
                $rowData[2],
                'El dato registrado no es una opción permitida.'
            );
        }

        // 7. Fecha de egreso del usuario a la institución (columna 16)
        // Valor obligatorio
        if (empty($rowData[16])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AP_ERROR_009',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[16],
                $rowData[16],
                'El dato registrado es obligatorio.'
            );
        }

        // 8. Hora de egreso del usuario de la institución (columna 17)
        // Valor obligatorio
        if (empty($rowData[17])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AP_ERROR_010',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[17],
                $rowData[17],
                'El dato registrado es obligatorio.'
            );
        }

        // logMessage(ErrorCollector::getErrors($keyErrorRedis));
    }
}
