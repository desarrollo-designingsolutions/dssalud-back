<?php

namespace App\Helpers\Radicaciones\Antiguas;

use App\Helpers\Constants;
use App\Helpers\Radicaciones\Common\ErrorCollector;
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
        // Obtener la ruta completa del archivo en el servidor
        $fullFilePath = Storage::disk(Constants::DISK_FILES)->path($zipPath);

        ErrorCollector::clear();

        if (!ZipValidator::validate($fullFilePath)) {
            return ErrorCollector::getErrors();
        }

        if (!ZipContentValidator::validate($fullFilePath)) {
            return ErrorCollector::getErrors();
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

        return ErrorCollector::getErrors();
    }
}
