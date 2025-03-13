<?php

namespace App\Helpers\FilingOld;

use App\Helpers\Common\ErrorCollector;
use Illuminate\Support\Facades\Redis;

class AMFileValidator
{
    /**
     * Valida el archivo CT y sus columnas.
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
            "columna 2: Fecha de remisión",
            "columna 3: Código del archivo",
            "columna 4: Total de registros",
        ];

        $isValid = true;




        // 1. Validar codigo_prestador (columna 1)
        if ( rowData[0]) == 'CC' &&!ctype_digit($rowData[0])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_CT_ERROR_001',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[0],
                $rowData[0],
                'El valor registrado no es numerico.'
            );
            $isValid = false;
        }


        return $isValid;
    }

}
