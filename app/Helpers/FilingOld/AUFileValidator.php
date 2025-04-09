<?php

namespace App\Helpers\FilingOld;

use App\Helpers\Common\ErrorCollector;

class AUFileValidator
{
    /**
     * Valida el archivo AU y sus columnas.
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
            'columna 5: Fecha de ingreso del usuario a observacion',
            'columna 6: Hora de ingreso del usuario a observacion',
            'columna 7: Numero de autorizacion',
            'columna 8: Causa externa',
            'columna 9: Diagnostico a la salida',
            'columna 10: Diagnóstico relacionado Nro. 1 a la salida',
            'columna 11: Diagnóstico relacionado Nro. 2 a la salida',
            'columna 12: Diagnóstico relacionado Nro. 3 a la salida',
            'columna 13: Destino del usuario a la salida de observación',
            'columna 14: Estado a la salida',
            'columna 15: Causa básica de muerte en urgencias',
            'columna 16: Fecha de la salida del usuario en observación',
            'columna 17: Hora de la salida del usuario en observación',
        ];

        // VALIDAR Número de la factura
        if (empty($rowData[0])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AU_ERROR_001',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[0],
                $rowData[0],
                'El dato registrado es obligatorio.'
            );
        }

        // VALIDAR Código del prestador de servicios de salud
        if (empty($rowData[1])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AU_ERROR_002',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[1],
                $rowData[1],
                'El codigo de prestador de servicio es un dato obligatorio.'
            );
        }

        // VALIDAR Tipo de identificación del usuario
        $allowedTypes = ['CC', 'CE', 'CD', 'PA', 'SC', 'PE', 'RE', 'RC', 'TI', 'CN', 'AS', 'MS', 'DE', 'PT', 'SI'];
        if (! in_array($rowData[2], $allowedTypes)) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AU_ERROR_003',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[2],
                $rowData[2],
                'El dato registrado no es un valor permitido.'
            );
        }

        // VALIDAR Fecha de ingreso del usuario a observacion
        if (empty($rowData[4])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AU_ERROR_004',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[4],
                $rowData[4],
                'El dato registrado no es un valor permitido.'
            );
        }

        // VALIDAR Hora de ingreso del usuario a observacion
        if (empty($rowData[5])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AU_ERROR_005',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[5],
                $rowData[5],
                'La Hora de ingreso del usuario a observacion es un dato obligatorio.'
            );
        }

        // VALIDAR Causa externa
        $allowedTypes = ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12', '13', '14', '15'];
        if (! in_array($rowData[7], $allowedTypes)) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AC_ERROR_006',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[7],
                $rowData[7],
                'El valor registrado no es un valor permitido.'
            );
        }

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
                'El codigo de causa externa es un valor obligatorio.'
            );
        }

        // VALIDAR Diagnostico a la salida
        if (empty($rowData[8])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AC_ERROR_008',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[8],
                $rowData[8],
                'El diagnostico de salida es un dato obligatorio.'
            );
        }

        // VALIDAR Destino del usuario a la salida de observación
        if (empty($rowData[12])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AC_ERROR_009',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[12],
                $rowData[12],
                'El Destino del usuario a la salida de observación es un dato obligatorio.'
            );
        }

        // VALIDAR Fecha de la salida del usuario en observación
        if (empty($rowData[15])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AC_ERROR_010',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[15],
                $rowData[15],
                'El Destino del usuario a la salida de observación es un dato obligatorio.'
            );
        }

        // VALIDAR Hora de la salida del usuario en observación
        if (empty($rowData[16])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AC_ERROR_011',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[16],
                $rowData[16],
                'El Destino del usuario a la salida de observación es un dato obligatorio.'
            );
        }

        // logMessage(ErrorCollector::getErrors($keyErrorRedis));
    }
}
