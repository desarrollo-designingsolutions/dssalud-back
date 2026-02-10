<?php

namespace App\Helpers\Common;

use App\Events\ProgressCircular;
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
        string $user_id,
        string $keyErrorRedis,
        string $filePath,
        int $expectedColumns,
        string $prefix,
    ): bool {
        // Eliminar la clave de errores en Redis
        Redis::del($keyErrorRedis);

        // Abrir el archivo en modo lectura
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

        // Contar el número total de líneas para calcular el progreso
        $totalLines = 0;
        while (! feof($handle)) {
            $line = fgets($handle);
            if ($line !== false && trim($line) !== '') {
                $totalLines++;
            }
        }
        rewind($handle); // Volver al inicio del archivo

        $rowNum = 1;
        $processedLines = 0;
        $hasError = false;

        // Procesar cada línea del archivo
        while (! feof($handle)) {
            $rawLine = fgets($handle);
            if ($rawLine === false || trim($rawLine) === '') {
                $rowNum++;

                continue;
            }

            // Eliminar BOM si existe
            $rawLine = ltrim($rawLine, "\xEF\xBB\xBF");

            // Dividir la línea en columnas
            $columns = str_getcsv($rawLine, ';');

            // Validar el número de columnas
            if (count($columns) !== $expectedColumns) {
                ErrorCollector::addError(
                    $keyErrorRedis,
                    'CSV_ERROR_003',
                    'R',
                    null,
                    basename($filePath),
                    $rowNum,
                    trim($rawLine),
                    null,
                    "Se esperaban {$expectedColumns} columnas, pero se encontraron ".count($columns).'.'
                );
                $hasError = true;
            }

            $processedLines++;
            $progress = ($processedLines / $totalLines) * 100;

            // Emitir el evento de progreso
            ProgressCircular::dispatch("csv_import_progress_{$prefix}.{$user_id}", $progress);

            $rowNum++;
        }

        fclose($handle);

        return ! $hasError;
    }
}
