<?php

namespace App\Helpers\FilingOld;

use App\Helpers\Common\ErrorCollector;

class ANFileValidator
{
    /**
     * Valida el archivo AN y sus columnas.
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
            'columna 3: Tipo de identificación de la madre',
            'columna 4: Numero de identificacion de la madre en el Sistema',
            'columna 5: Fecha de nacimiento del recién nacido',
            'columna 6: Hora de nacimiento',
            'columna 7: Edad gestacional',
            'columna 8: Control prenatal',
            'columna 9: Sexo',
            'columna 10: Peso',
            'columna 11: Diagnóstico del recién nacido',
            'columna 12: Causa básica de muerte',
            'columna 13: Fecha de muerte del recién nacido',
            'columna 14: Hora de muerte del recién nacido',
        ];

        // validar  Número de la factura
        if (empty($rowData[0])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AN_ERROR_001',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[0],
                $rowData[0],
                'El dato registrado es obligatorio.'
            );
        }
        // validar  Código del prestador de servicios de salud
        if (empty($rowData[1])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AN_ERROR_002',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[1],
                $rowData[1],
                'El dato registrado es obligatorio.'
            );
        }

        // validar Tipo de identificación de la madre
        $allowedTypes = ['CC', 'CE', 'CD', 'PA', 'SC', 'PE', 'RE', 'RC', 'TI', 'CN', 'AS', 'MS', 'DE', 'PT', 'SI'];
        if (! in_array($rowData[2], $allowedTypes)) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AC_ERROR_003',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[2],
                $rowData[2],
                'El dato registrado no es un valor permitido.'
            );
        }

        // validar Fecha de nacimiento del recién nacido
        if (empty($rowData[4])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AC_ERROR_004',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[4],
                $rowData[4],
                'El dato registrado es obligatorio.'
            );
        }

        // validar Hora de nacimiento
        if (empty($rowData[5])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AC_ERROR_005',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[5],
                $rowData[5],
                'El dato registrado es obligatorio.'
            );
        }
        // validar Edad gestacional
        if (empty($rowData[6])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AC_ERROR_006',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[6],
                $rowData[6],
                'El dato registrado es obligatorio.'
            );
        }
        // validar Control prenatal
        if (empty($rowData[7])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AC_ERROR_007',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[7],
                $rowData[7],
                'El dato registrado es obligatorio.'
            );
        }
        $allowedTypes = ['1', '2'];
        if (! in_array($rowData[7], $allowedTypes)) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AC_ERROR_008',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[7],
                $rowData[7],
                'El dato registrado no es un valor permitido.'
            );
        }

        // validar Sexo
        if (empty($rowData[8])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AC_ERROR_009',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[8],
                $rowData[8],
                'El dato registrado es obligatorio.'
            );
        }

        $allowedTypes = ['1', '2'];
        if (! in_array($rowData[8], $allowedTypes)) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AC_ERROR_010',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[8],
                $rowData[8],
                'El dato registrado no es un valor permitido.'
            );
        }

        // validar Peso
        if (empty($rowData[9])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AC_ERROR_011',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[9],
                $rowData[9],
                'El dato registrado es obligatorio.'
            );
        }

        // logMessage(ErrorCollector::getErrors($keyErrorRedis));
    }
}
