<?php

namespace App\Helpers\FilingOld;

use App\Helpers\Common\ErrorCollector;
use ZipArchive;

class ZipContentValidator
{
    /**
     * Valida el contenido del ZIP (cantidad y tipos de archivos).
     *
     * @param  string  $filePath  Ruta del archivo ZIP
     * @return bool Verdadero si pasa las validaciones, falso si hay errores
     */
    public static function validate(string $filePath, string $uniqid)
    {
        $keyErrorRedis = "filingOld:{$uniqid}:errors";

        $zip = new ZipArchive;
        if ($zip->open($filePath) !== true) {
            return false;
        }

        $fileCount = $zip->numFiles;
        $fileNames = []; // Para almacenar nombres de archivos
        $folderNames = []; // Para almacenar nombres de carpetas

        for ($i = 0; $i < $fileCount; $i++) {
            $fileName = $zip->getNameIndex($i);
            if ($fileName) {
                if (substr($fileName, -1) === '/') {
                    // Es una carpeta (termina en '/')
                    $folderNames[] = rtrim($fileName, '/'); // Quitamos el '/' final para un nombre más limpio
                } else {
                    // Es un archivo
                    $fileNames[] = $fileName;
                }
            }
        }

        // no se aceptan carpetas
        if (count($folderNames) > 0) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'ZIP_ERROR_015',
                'R',
                null,
                basename($filePath),
                null,
                null,
                count($fileNames),
                'El archivo ZIP contiene una carpeta lo cual no es valido.'
            );
            $zip->close();

            return false;
        }
        // 4. Máximo 10 archivos
        if (count($fileNames) > 10) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'ZIP_ERROR_003',
                'R',
                null,
                basename($filePath),
                null,
                null,
                count($fileNames),
                'El archivo ZIP contiene más de 10 archivos. Reduzca la cantidad a máximo 10 archivos.'
            );
            $zip->close();

            return false;
        }

        // 5. Mínimo 4 archivos
        if (count($fileNames) < 4) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'ZIP_ERROR_004',
                'R',
                null,
                basename($filePath),
                null,
                null,
                count($fileNames),
                'El archivo ZIP contiene menos de 4 archivos. Asegúrese de incluir al menos 4 archivos.'
            );
            $zip->close();

            return false;
        }

        // 6. Verificar tipos de archivos (AF, US, y al menos uno de AC/AP/AM/AT)
        $hasAF = false;
        $hasUS = false;
        $hasCT = false;
        $hasACorSimilar = false;

        foreach ($fileNames as $fileName) {
            $prefix = strtoupper(substr(basename($fileName), 0, 2));
            if ($prefix === 'AF') {
                $hasAF = true;
            }
            if ($prefix === 'US') {
                $hasUS = true;
            }
            if ($prefix === 'CT') {
                $hasCT = true;
            }
            if (in_array($prefix, ['AC', 'AP', 'AM', 'AT'])) {
                $hasACorSimilar = true;
            }
        }

        if (! $hasAF) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'ZIP_ERROR_005',
                'R',
                null,
                basename($filePath),
                null,
                null,
                null,
                'Falta un archivo que inicie con AF. Incluya al menos un archivo AF en el ZIP.'
            );
        }
        if (! $hasUS) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'ZIP_ERROR_006',
                'R',
                null,
                basename($filePath),
                null,
                null,
                null,
                'Falta un archivo que inicie con US. Incluya al menos un archivo US en el ZIP.'
            );
        }
        if (! $hasCT) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'ZIP_ERROR_007',
                'R',
                null,
                basename($filePath),
                null,
                null,
                null,
                'Falta un archivo que inicie con CT. Incluya al menos un archivo CT en el ZIP.'
            );
        }
        if (! $hasACorSimilar) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'ZIP_ERROR_008',
                'R',
                null,
                basename($filePath),
                null,
                null,
                null,
                'Falta un archivo que inicie con AC, AP, AM o AT. Incluya al menos un archivo de estos tipos en el ZIP.'
            );
        }

        $zip->close();

        return $hasAF && $hasUS && $hasACorSimilar;
    }
}
