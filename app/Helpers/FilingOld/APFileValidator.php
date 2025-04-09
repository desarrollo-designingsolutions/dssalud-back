<?php

namespace App\Helpers\FilingOld;

use App\Helpers\Common\ErrorCollector;

class APFileValidator
{
    /**
     * Valida el archivo AP y sus columnas.
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
            'columna 5: Fecha del procedimiento',
            'columna 6: Numero de autorizacion',
            'columna 7: Código del procedimiento',
            'columna 8: Ambito de realización del procedimiento',
            'columna 9: Finalidad del procedimiento',
            'columna 10: Personal que atiende',
            'columna 11: Diagnóstico principal',
            'columna 12: Diagnostico relacionado',
            'columna 13: Complicación',
            'columna 14: Forma de realización del acto quirúrgico',
            'columna 15: Valor del procedimiento',
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
                'El numero de factura es un dato obligatorio'
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

        // 3. Tipo de identificación del usuario (columna 2)
        // Valor obligatorio
        if (empty($rowData[2])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AP_ERROR_003',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[2],
                $rowData[2],
                'El dato registrado es obligatorio.'
            );
        }

        // Unicamente los valores permitidos
        $allowedPrefixes = ['CC', 'CE', 'CD', 'PA', 'SC', 'PE', 'RE', 'RC', 'TI', 'CN', 'AS', 'MS', 'DE', 'PT', 'SI'];
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

        // 4. Número de identificación del usuario en el sistema (columna 3)
        // Valor obligatorio
        if (empty($rowData[3])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AP_ERROR_005',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[3],
                $rowData[3],
                'El dato registrado es obligatorio.'
            );
        }

        // 5. Fecha del procedimiento (columna 4)
        // Valor obligatorio
        if (empty($rowData[4])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AP_ERROR_006',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[4],
                $rowData[4],
                'El dato registrado es obligatorio.'
            );
        }

        // 6. Código del procedimiento (columna 6)
        // Valor obligatorio
        if (empty($rowData[6])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AP_ERROR_007',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[6],
                $rowData[6],
                'El dato registrado es obligatorio.'
            );
        }

        // 7. Ambito de realización del procedimiento (columna 7)
        // Valor obligatorio
        if (empty($rowData[7])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AP_ERROR_008',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[7],
                $rowData[7],
                'El dato registrado es obligatorio.'
            );
        }

        // 8. Valor del procedimiento (columna 14)
        // Valor obligatorio
        if (empty($rowData[14])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AP_ERROR_009',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[14],
                $rowData[14],
                'El dato registrado es obligatorio.'
            );
        }

        // logMessage(ErrorCollector::getErrors($keyErrorRedis));
    }
}
