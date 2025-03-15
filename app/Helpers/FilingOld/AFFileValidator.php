<?php

namespace App\Helpers\FilingOld;

use App\Helpers\Common\ErrorCollector;
use Illuminate\Support\Facades\Redis;

class AFFileValidator
{
    /**
     * Valida el archivo AF y sus columnas.
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
            'columna 1: Código del prestador de servicios de salud',
            'columna 2: Razón social o apellidos y nombre del prestador de servicios de salud',
            'columna 3: Tipo de identificación del prestador de servicios de salud',
            'columna 4: Número de identificación del prestador',
            'columna 5: Número de la factura',
            'columna 6: Fecha de expedición de la factura',
            'columna 7: Fecha de inicio',
            'columna 8: Fecha final',
            'columna 9: Código entidad administradora',
            'columna 10: Nombre entidad administradora',
            'columna 11: Número del contrato',
            'columna 12: Plan de beneficios',
            'columna 13: Número de la póliza',
            'columna 14: Valor total del pago compartido (copago)',
            'columna 15: Valor de la comisión',
            'columna 16: Valor total de descuentos',
            'columna 17: Valor neto a pagar por la entidad contratante',
        ];

        // 1. Validar código del prestador de servicios de salud (columna 1)
        // Valor obligatorio
        if (empty($rowData[0])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AF_ERROR_001',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[0],
                $rowData[0],
                'El dato registrado es obligatorio.'
            );
        }

        // Que sea el mismo registrado en el archivo de control. Debe ser igual en todos los registros del archivo AF
        $numberInvoiceCT = self::getNumberInvoiceCT($filing_id);
        if (! empty($numberInvoiceCT)) {
            // Se valida que el número de factura sea igual en todos los registros del archivo AF
            $validationNumberInvoice = self::validationNumberInvoice($filing_id, $numberInvoiceCT);

            if ($validationNumberInvoice === false) {
                ErrorCollector::addError(
                    $keyErrorRedis,
                    'FILE_AF_ERROR_002',
                    'R',
                    null,
                    $fileName,
                    $rowNumber,
                    $titleColumn[0],
                    $rowData[0],
                    'El dato registrado no es igual al informado en el archivo AF'
                );
            }
        } else {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AF_ERROR_002',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[0],
                $rowData[0],
                'El dato registrado no es igual al informado en el archivo AF'
            );
        }

        // 2. Razón social o apellidos y nombre del prestador de servicios de salud (columna 2)
        // Valor obligatorio
        if (empty($rowData[1])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AF_ERROR_003',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[1],
                $rowData[1],
                'El dato registrado es obligatorio.'
            );
        }

        // 3. Tipo de identificación del prestador de servicios de salud (columna 3)
        // Valor obligatorio
        if (empty($rowData[2])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AF_ERROR_004',
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
        $allowedPrefixes = ['NI', 'CC', 'CE', 'CD', 'PA', 'PE'];
        if (! in_array($rowData[2], $allowedPrefixes)) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AF_ERROR_005',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[2],
                $rowData[2],
                'El dato ingresado no es permitido'
            );
        }

        // 4. Número de identificación del prestador (columna 4)
        // Valor obligatorio
        if (empty($rowData[3])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AF_ERROR_006',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[3],
                $rowData[3],
                'El dato registrado es obligatorio.'
            );
        }

        // 5. Número de la factura (columna 5)
        // Valor obligatorio
        if (empty($rowData[4])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AF_ERROR_007',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[4],
                $rowData[4],
                'El dato registrado es obligatorio.'
            );
        }

        // 6. Fecha de expedición de la factura (columna 6)
        // Valor obligatorio
        if (empty($rowData[5])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AF_ERROR_008',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[5],
                $rowData[5],
                'El dato registrado es obligatorio.'
            );
        }

        // 7. Fecha de expedición de la factura (columna 7)
        // Valor obligatorio
        if (empty($rowData[6])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AF_ERROR_009',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[6],
                $rowData[6],
                'El dato registrado es obligatorio.'
            );
        }

        // 8. Fecha de expedición de la factura (columna 8)
        // Valor obligatorio
        if (empty($rowData[7])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AF_ERROR_010',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[7],
                $rowData[7],
                'El dato registrado es obligatorio.'
            );
        }

        // 9. Fecha de inicio (columna 9)
        // Valor obligatorio
        if (empty($rowData[8])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_AF_ERROR_011',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[8],
                $rowData[8],
                'El dato registrado es obligatorio.'
            );
        }

        // logMessage(ErrorCollector::getErrors($keyErrorRedis));
    }

    private static function getNumberInvoiceCT($filing_id): bool|string
    {
        $contentDataArrayCt = json_decode(Redis::get("filingOld:{$filing_id}:CT"), 1);

        // El arreglo normal convertido
        $normalArray = array_map(function ($item) {
            return explode(',', str_replace('\/', '/', $item));
        }, $contentDataArrayCt);

        // Filtrar el arreglo para encontrar el elemento deseado
        $filteredArray = array_filter($normalArray, function ($item) {
            return strpos($item[2], 'AF') === 0;
        });

        // Obtener el valor de la posición 0 del elemento filtrado
        $desiredValue = '';
        if (! empty($filteredArray)) {
            $firstItem = reset($filteredArray); // Obtener el primer elemento del arreglo filtrado
            $desiredValue = $firstItem[0];
        }

        // Imprimir el resultado
        if ($desiredValue !== '') {
            return $desiredValue;
        } else {
            return false;
        }
    }

    private static function validationNumberInvoice($filing_id, $search)
    {
        $contentDataArrayAf = json_decode(Redis::get("filingOld:{$filing_id}:AF"), 1);

        // Convertir cada cadena en un arreglo
        $processedDataArrayAf = array_map(fn ($item) => explode(',', str_replace('\/', '/', $item)), $contentDataArrayAf);

        // Extraer la primera columna (posiciones 0) de todos los sub-arreglos
        $firstColumnAf = array_column($processedDataArrayAf, 0);

        // Verificar si todas las posiciones son iguales a la variable específica
        return array_reduce($firstColumnAf, fn ($carry, $item) => $carry && ($item === $search), true);
    }
}
