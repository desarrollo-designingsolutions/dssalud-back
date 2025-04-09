<?php

namespace App\Helpers\FilingOld;

use App\Helpers\Common\ErrorCollector;

class ACFileValidator
{
    /**
     * Valida el archivo AC y sus columnas.
     *
     * @param  string  $fileName  Nombre del archivo
     * @param  string  $rowData  Datos de la fila del txt a validar (como cadena CSV)
     * @param  int  $rowNumber  Número de la fila del txt a validar
     * @param  string  $filing_id  ID del proceso
     * @return bool
     */
    public static function validate(string $fileName, string $rowData, int $rowNumber, string $filing_id): void
    {
        $keyErrorRedis = "filingOld:{$filing_id}:errors";

        // Dividir la fila en columnas
        $rowData = array_map('trim', explode(',', $rowData));

        $titleColumn = [
            'Columna 1: Número de la factura',
            'Columna 2: Código del prestador de servicios de salud',
            'Columna 3: Tipo de identificación del usuario',
            'Columna 4: Número de identificación del usuario en el sistema',
            'Columna 5: Fecha de la consulta',
            'Columna 6: Número de autorización',
            'Columna 7: Código de la consulta',
            'Columna 8: Finalidad de la consulta',
            'Columna 9: Causa externa',
            'Columna 10: codigo de diagnostico principal',
            'Columna 11: Código del diagnóstico relacionado No. 1',
            'Columna 12: Código del diagnóstico relacionado No. 2',
            'Columna 13: Código del diagnóstico relacionado No. 3',
            'Columna 14: Tipo de diagnóstico principal',
            'Columna 15: Valor de la consulta',
            'Columna 16: Valor de la cuota moderadora',
            'Columna 17: Valor neto a pagar',
        ];

        // validar Número de la factura
        if (empty($rowData[0])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AC_ERROR_001',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[0],
                $rowData[0],
                'El numero de factura es un dato obligatorio.'
            );
        }

        // validar Código del prestador de servicios de salud
        if (empty($rowData[1])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AC_ERROR_002',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[1],
                $rowData[1],
                'El codigo de prestador de servicio es un dato obligatorio.'
            );
        }

        // validar Tipo de identificación del usuario
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

        // validar Fecha de la consulta
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
                'El codigo de prestador de servicio es un dato obligatorio.'
            );
        }

        // validar Código de la consulta
        $allowedTypes = ['890201', '890202', '890301', '890302', '890701', '890702', '890101', '890102', '890203', '890204', '890304', '890303', '890703', '890704', '890205', '890305', '890105', '890206', '890208', '890207', '890209', '890210', '890211', '890212', '890213', '890306', '890307', '890308', '890309', '890310', '890311', '890312', '890313', '890402', '890403', '890404', '890405', '890406', '890408', '890409', '890410', '890411', '890412', '890413', '890501', '890502', '890503', '890214', '890314', '890208', '890209', '890284', '890285', '890202', '890308', '890309', '890384', '890385', '890302', '940100', '940200', '940301', '940700', '940900', '941100', '941301', '941400', '942600', '943101', '943102', '943500', '944001', '944002', '944101', '944102', '944201', '944202', '944901', '944902', '944903', '944904', '944905', '944906', '944910', '944915'];

        if (! in_array($rowData[6], $allowedTypes)) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AC_ERROR_005',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[6],
                $rowData[6],
                'El registro del codigo de la consulta es obligatorio.'
            );
        }

        // validar Finalidad de la consulta
        $allowedTypes = ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10'];
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
                'Debe registrar un valor numerico de dos caracteres entre el 01 y el 10.'
            );
        }

        // validar Causa externa
        $allowedTypes = ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12', '13', '14', '15'];
        if (! in_array($rowData[8], $allowedTypes)) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AC_ERROR_007',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[8],
                $rowData[8],
                'Debe registrar un valor numerico de dos caracteres entre el 01 y el 10.'
            );
        }

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
                'El codigo de causa externa es un valor obligatorio.'
            );
        }

        // validar codigo de diagnostico principal
        if (empty($rowData[9])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AC_ERROR_009',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[9],
                $rowData[9],
                'El código de diagnostico principal es un dato obligatorio.'
            );
        }

        // validar Tipo de diagnóstico principal
        if (empty($rowData[13])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AC_ERROR_011',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[13],
                $rowData[13],
                'El tipo de diagnostico principal es un dato obligatorio.'
            );
        }

        $allowedTypes = [1, 2, 3];
        if (! in_array($rowData[13], $allowedTypes)) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AC_ERROR_012',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[13],
                $rowData[13],
                'Debe registrar un valor numerico de dos caracteres entre el 01 y el 10.'
            );
        }

        // validar Valor de la consulta
        if (empty($rowData[14])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AC_ERROR_013',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[14],
                $rowData[14],
                'El valor de la consulta es un dato obligatorio.'
            );
        }

        // validar Valor neto a pagar
        if (empty($rowData[16])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AC_ERROR_013',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[16],
                $rowData[16],
                'El valor neto a pagar es un dato obligatorio.'
            );
        }

        // logMessage(ErrorCollector::getErrors($keyErrorRedis));
    }
}
