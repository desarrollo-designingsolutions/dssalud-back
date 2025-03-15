<?php

namespace App\Helpers\FilingOld;

use App\Helpers\Common\ErrorCollector;

class InternalFileValidator
{
    // Cantidad esperada de columnas por tipo de archivo (ajústalas según tus necesidades)
    private static $expectedColumns = [
        'AC' => 17,
        'AF' => 17,
        'AH' => 19,
        'AM' => 14,
        'AN' => 14,
        'AP' => 15,
        'AT' => 11,
        'AU' => 17,
        'US' => 14,
        'CT' => 4,
    ];

    /**
     * Valida las columnas de un archivo extraído del ZIP.
     *
     * @param  string  $uniqid  id unico del proceso
     * @param  string  $filePath  Ruta del archivo extraído
     * @return bool Verdadero si pasa las validaciones, falso si hay errores
     */
    public static function validate(string $uniqid, string $filePath): bool
    {
        $keyErrorRedis = "filingOld:{$uniqid}:errors";

        $fileName = basename($filePath);
        $prefix = strtoupper(substr($fileName, 0, 2));
        $expected = self::$expectedColumns[$prefix] ?? null;

        if ($expected === null) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'ZIP_ERROR_009',
                'R',
                null,
                $fileName,
                null,
                null,
                null,
                'Tipo de archivo no reconocido. Verifique que el nombre del archivo inicie con AF, US, AC,CT, AP, AM , AH, AN, AT, AU.'
            );

            return false;
        }

        $handle = fopen($filePath, 'r');
        if (! $handle) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'ZIP_ERROR_010',
                'R',
                null,
                $fileName,
                null,
                null,
                null,
                'No se pudo abrir el archivo. Asegúrese de que el archivo sea legible.'
            );

            return false;
        }

        $row = 1;
        $isValid = true;

        while (($line = fgetcsv($handle, 0, ',')) !== false) {
            $actualColumns = count($line);
            if ($actualColumns !== $expected) {
                // Suponiendo que num_invoice está en la primera columna (ajústalo según tu formato)
                $numInvoice = $line[0] ?? null;
                ErrorCollector::addError(
                    $keyErrorRedis,
                    'ZIP_ERROR_011',
                    'R',
                    $numInvoice,
                    $fileName,
                    $row,
                    null, // No especificamos columna aquí, pero podrías ajustarlo
                    $actualColumns,
                    "El registro debe tener $expected columnas y tiene $actualColumns. Verifique el formato del archivo y ajuste las columnas."
                );
                $isValid = false;
            }
            $row++;
        }

        fclose($handle);

        return $isValid;
    }
}
