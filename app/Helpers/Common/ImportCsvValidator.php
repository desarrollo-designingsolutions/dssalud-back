<?php

namespace App\Helpers\Common;

use App\Helpers\Common\ErrorCollector;
use Illuminate\Support\Facades\Redis;

class ImportCsvValidator
{
    /**
     * Valida las columnas de un archivo extraído del ZIP.
     *
     * @param  string  $uniqid  id unico del proceso
     * @param  string  $filePath  Ruta del archivo extraído
     * @return bool Verdadero si pasa las validaciones, falso si hay errores
     */
    public static function validate(
        string $keyErrorRedis,
        string $filePath,
        int $expectedColumns = 5
    ): bool {

        //Se elimina la llave de errores
        Redis::del($keyErrorRedis); 

        // 1. Abrir archivo en modo lectura
        $handle = @fopen($filePath, 'r');
        if ($handle === false) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'CSV_ERROR_001',
                'R',
                null,
                basename($filePath),
                null,
                null,
                null,
                'No se pudo abrir el archivo. Asegúrese de que sea legible.'
            );
            return false;
        }

        $rowNum   = 1;
        $hasError = false;

        // 2. Recorrer hasta fin de archivo
        while (! feof($handle)) {
            $rawLine = fgets($handle);                  // Lee línea bruta :contentReference[oaicite:5]{index=5}
            if ($rawLine === false) {
                break;
            }

            // 3. Eliminar BOM en la primera columna, si existe
            $rawLine = ltrim($rawLine, "\xEF\xBB\xBF"); // :contentReference[oaicite:6]{index=6}

            // Saltar líneas vacías
            if (trim($rawLine) === '') {
                $rowNum++;
                continue;
            }

            // 4. Partir la línea en campos con ';'
            $columns = str_getcsv($rawLine, ';');       // :contentReference[oaicite:7]{index=7}

            // 5. Validar número de columnas exacto
            if (count($columns) !== $expectedColumns) {  // :contentReference[oaicite:8]{index=8}
                ErrorCollector::addError(
                    $keyErrorRedis,
                    'CSV_ERROR_003',
                    'R',
                    $rowNum,
                    basename($filePath),
                    null,
                    trim($rawLine),
                    null,
                    "Se esperaban {$expectedColumns} columnas, pero se encontraron " . count($columns) . "."
                );
                $hasError = true;
            }

            // 6. Detectar comas no deseadas en la línea
            if (strpos($rawLine, ',') !== false) {      // :contentReference[oaicite:9]{index=9}
                ErrorCollector::addError(
                    $keyErrorRedis,
                    'CSV_ERROR_004',
                    'W',
                    $rowNum,
                    basename($filePath),
                    null,
                    trim($rawLine),
                    null,
                    "Se encontró una coma (',') en la fila {$rowNum}."
                );
                $hasError = true;
            }

            $rowNum++;
        }

        fclose($handle);

        return ! $hasError;
    }
}
