<?php

namespace App\Helpers\FilingOld;

use App\Helpers\Common\ErrorCollector;

class ACFileValidator
{
    /**
     * Valida el archivo AC y sus columnas.
     *
     * @param string $fileName Nombre del archivo
     * @param string $rowData Datos de la fila del txt a validar (como cadena CSV)
     * @param int $rowNumber Número de la fila del txt a validar
     * @param string $filing_id ID del proceso
     * @return bool
     */
    public static function validate(string $fileName, string $rowData, int $rowNumber, string $filing_id): void
    {
        $keyErrorRedis = "filingOld:{$filing_id}:errors";

        // Dividir la fila en columnas
        $rowData = array_map('trim', explode(",", $rowData));

        $titleColumn = [
            "Columna 1: Número de la factura",
            "Columna 2: Código del prestador de servicios de salud",
            "Columna 3: Tipo de identificación del usuario",
            "Columna 4: Número de identificación del usuario en el sistema",
            "Columna 5: Fecha de la consulta",
            "Columna 6: Número de autorización",
            "Columna 7: Código de la consulta",
            "Columna 8: Finalidad de la consulta",
            "Columna 9: Causa externa",
            "Columna 10: codigo de diagnostico principal",
            "Columna 11: Código del diagnóstico relacionado No. 1",
            "Columna 12: Código del diagnóstico relacionado No. 2",
            "Columna 13: Código del diagnóstico relacionado No. 3",
            "Columna 14: Tipo de diagnóstico principal",
            "Columna 15: Valor de la consulta",
            "Columna 16: Valor de la cuota moderadora",
            "Columna 17: Valor neto a pagar",
        ];


        //validar Número de la factura
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



        logMessage(ErrorCollector::getErrors($keyErrorRedis));
    }
}
