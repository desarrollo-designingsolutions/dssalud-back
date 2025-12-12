<?php

namespace App\Helpers\FilingOld;

use App\Helpers\Common\ErrorCollector;
use ZipArchive;

class ZipValidator
{
    /**
     * Valida las características básicas del archivo ZIP.
     *
     * @param  string  $filePath  Ruta del archivo ZIP
     * @return bool Verdadero si pasa las validaciones, falso si hay errores
     */
    public static function validate(string $filePath, string $uniqid): bool
    {
        $keyErrorRedis = "filingOld:{$uniqid}:errors";

        ErrorCollector::clear($keyErrorRedis);

        // 1. Verificar que sea un archivo y tenga extensión .zip
        if (! file_exists($filePath) || pathinfo($filePath, PATHINFO_EXTENSION) !== 'zip') {
            ErrorCollector::addError(
                $keyErrorRedis,
                'ZIP_ERROR_001', // Código de validación (ajústalo según tu sistema)
                'R',      // Tipo de validación (R = rechaza si falla, ajusta según necesidad)
                null,     // No hay num_invoice en esta etapa
                basename($filePath),
                null,     // Sin fila
                null,     // Sin columna
                null,     // Sin datos específicos
                'El archivo no es un ZIP válido o no tiene extensión .zip. Asegúrese de enviar un archivo con extensión .zip.'
            );

            return false;
        }

        // 2. Verificar que se pueda abrir
        $zip = new ZipArchive;
        if ($zip->open($filePath) !== true) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'ZIP_ERROR_002',
                'R',
                null,
                basename($filePath),
                null,
                null,
                null,
                'El archivo ZIP no se puede abrir o está corrupto. Verifique que el archivo no esté dañado y vuelva a intentarlo.'
            );

            return false;
        }

        $zip->close();

        return true;
    }
}
