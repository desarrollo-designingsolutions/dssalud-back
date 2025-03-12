<?php

namespace App\Helpers\FilingOld;

use App\Helpers\Constants;
use App\Helpers\Common\ErrorCollector;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class ValidationOrchestrator
{
    /**
     * Ejecuta el proceso completo de validación del ZIP y sus archivos.
     *
     * @param string $zipPath Ruta del archivo ZIP
     * @return array Errores encontrados
     */
    public static function validate(string $zipPath)
    {
        $keyRedis = 'filingOld:{$filing->id}:errors';

        // Obtener la ruta completa del archivo en el servidor
        $fullFilePath = Storage::disk(Constants::DISK_FILES)->path($zipPath);

        ErrorCollector::clear($keyRedis);

        if (!ZipValidator::validate($fullFilePath)) {
            return ErrorCollector::getErrors($keyRedis);
        }

        if (!ZipContentValidator::validate($fullFilePath)) {
            return ErrorCollector::getErrors($keyRedis);
        }

        $zip = new ZipArchive();
        $zip->open($fullFilePath);
        $tempDir = sys_get_temp_dir() . '/' . uniqid();
        mkdir($tempDir);
        $zip->extractTo($tempDir);

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $fileName = $zip->getNameIndex($i);
            if (substr($fileName, -1) !== '/') {
                $filePath = $tempDir . '/' . $fileName;
                InternalFileValidator::validate($filePath);
            }
        }

        $zip->close();

        // Limpiar el directorio temporal
        array_map('unlink', glob("$tempDir/*"));
        rmdir($tempDir);

        return ErrorCollector::getErrors($keyRedis);
    }
}
