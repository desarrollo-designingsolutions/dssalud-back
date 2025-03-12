<?php

namespace App\Helpers\FilingOld;

use App\Helpers\Common\ErrorCollector;
use Illuminate\Support\Facades\Redis;

class AFFileValidator
{
    /**
     * Valida el archivo AF y sus columnas.
     *
     * @param string $fileName Nombre del archivo
     * @param string $rowData datos de la fila del txt a validar
     * @param string $rowNumber numero de la fila del txt a validar
     * @param string $filing_id numero de la fila del txt a validar
     */
    public static function validate(string $fileName, string $rowData, $rowNumber, $filing_id)
    {
        $keyErrorRedis = "filingOld:{$filing_id}:errors";

        $rowData = explode(",", $rowData);

        $titleColumn = [
            "columna 1: Código del prestador de servicios de salud",
            "columna 2: Razón social o apellidos y nombre del prestador de servicios de salud",
            "columna 3: Tipo de identificación del prestador de servicios de salud",
            "columna 4: Número de identificación del prestador",
            "columna 5: Número de la factura",
            "columna 6: Fecha de expedición de la factura",
            "columna 7: Fecha de inicio",
            "columna 8: Fecha final",
            "columna 9: Código entidad administradora",
            "columna 10: Nombre entidad administradora",
            "columna 11: Número del contrato",
            "columna 12: Plan de beneficios",
            "columna 13: Número de la póliza",
            "columna 14: Valor total del pago compartido (copago)",
            "columna 15: Valor de la comisión",
            "columna 16: Valor total de descuentos",
            "columna 17: Valor neto a pagar por la entidad contratante"
        ];

        $isValid = true;

        $contentDataArrayCt = json_decode(Redis::get("filingOld:{$filing_id}:CT"), 1);
        $contentDataArrayAf = json_decode(Redis::get("filingOld:{$filing_id}:AF"), 1);

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
            $isValid = false;
        }

        // Que sea el mismo registrado en el archivo de control. Debe ser igual en todos los registros del archivo AF
        $numberInvoiceCT = self::getNumberInvoiceCT($contentDataArrayCt);
        if (!empty($numberInvoiceCT)) {
            // Se valida que el número de factura sea igual en todos los registros del archivo AF
            $validation = self::validationNumberInvoice($contentDataArrayAf, $numberInvoiceCT);

            if ($validation === false) {
                ErrorCollector::addError(
                    $keyErrorRedis,
                    'FILE_AF_ERROR_002',
                    'R',
                    null,
                    $fileName,
                    $rowNumber,
                    $titleColumn[0],
                    $rowData[0],
                    'El dato registrado no es igual al informado en el archivo AF.'
                );
                $isValid = false;
            }
        }

        return $isValid;
    }

    private static function getNumberInvoiceCT($contentDataArrayCt): bool|string
    {
        // El arreglo normal convertido
        $normalArray = array_map(function ($item) {
            return explode(",", str_replace('\/', '/', $item));
        }, $contentDataArrayCt);

        // Filtrar el arreglo para encontrar el elemento deseado
        $filteredArray = array_filter($normalArray, function ($item) {
            return strpos($item[2], 'AF') === 0;
        });

        // Obtener el valor de la posición 0 del elemento filtrado
        $desiredValue = '';
        if (!empty($filteredArray)) {
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

    private static function validationNumberInvoice($contentDataArrayAF, $search)
    {
        // Convertir cada cadena en un arreglo
        $processedDataArrayAf = array_map(fn($item) => explode(",", str_replace('\/', '/', $item)), $contentDataArrayAF);

        // Extraer la primera columna (posiciones 0) de todos los sub-arreglos
        $firstColumnAf = array_column($processedDataArrayAf, 0);

        // Verificar si todas las posiciones son iguales a la variable específica
        return array_reduce($firstColumnAf, fn($carry, $item) => $carry && ($item === $search), true);
    }
}
