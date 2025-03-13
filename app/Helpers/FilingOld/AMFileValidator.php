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





        return $isValid;
    }

}
