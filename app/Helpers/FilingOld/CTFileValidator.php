<?php

namespace App\Helpers\FilingOld;

use App\Helpers\Common\ErrorCollector;
use Illuminate\Support\Facades\Redis;

class CTFileValidator
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

        $codigoArchivos = json_decode(Redis::get("filingOld:{$filing_id}:validationCt_codigoArchivos"), 1);

        $tempDir = Redis::get("filingOld:{$filing_id}:tempZip");

        // 1. Validar codigo_prestador (columna 1)
        if (!ctype_digit($rowData[0])) {
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
        if (strlen($rowData[0]) !== 12) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_CT_ERROR_002',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[0],
                $rowData[0],
                'El dato registrado contiene una logitud diferente a 12 caracteres.'
            );
            $isValid = false;
        }

        // 2. Validar fecha (columna 2)
        if (!preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $rowData[1]) || !self::isValidDate($rowData[1])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_CT_ERROR_003',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[1],
                $rowData[1],
                'El dato registrado no usa el formato de fecha establecido.'
            );
            $isValid = false;
        } elseif (self::isDateAfterToday($rowData[1])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_CT_ERROR_004',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[1],
                $rowData[1],
                'La fecha registrada es mayor a la fecha actual.'
            );
            $isValid = false;
        }

        // 3. Validar codigo_archivo (columna 3)
        $prefix = strtoupper(substr($rowData[2], 0, 2));
        $allowedPrefixes = ["AC", "AF", "AH", "AM", "AN", "AP", "AT", "AU", "US", "CT"];
        if (!in_array($prefix, $allowedPrefixes)) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_CT_ERROR_005',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[2],
                $rowData[2],
                'El codigo de archivo no cumple con el formato permitido.'
            );
            $isValid = false;
        }

        if (in_array($rowData[2], $codigoArchivos)) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_CT_ERROR_006',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[2],
                $rowData[2],
                'El codigo de archivo solo puede ser registrado una vez por cada tipo.'
            );
            $isValid = false;
        } else {
            $codigoArchivos[] = $rowData[2];
            Redis::set("filingOld:{$filing_id}:validationCt_codigoArchivos", json_encode($codigoArchivos));
        }


        // 4. Validar total_registros (columna 4)
        if (!ctype_digit($rowData[3]) || strpos($rowData[3], '.') !== false) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_CT_ERROR_007',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[3],
                $rowData[3],
                'El valor registrado no es numerico.'
            );
            $isValid = false;
        } else {
            $fileToFind = $rowData[2] . ".txt";
            $expectedCount = (int) $rowData[3];
            $actualCount = self::countFileRows($tempDir, $fileToFind);
            if ($actualCount === null) {
                ErrorCollector::addError(
                    $keyErrorRedis,
                    'FILE_CT_ERROR_008',
                    'R',
                    null,
                    $fileName,
                    $rowNumber,
                    3,
                    $rowData[2],
                    'No se encontró el archivo correspondiente al código ' . $fileToFind . '. Verifique que exista en el ZIP.'
                );
                $isValid = false;
            } elseif ($actualCount !== $expectedCount) {
                ErrorCollector::addError(
                    $keyErrorRedis,
                    'FILE_CT_ERROR_009',
                    'R',
                    null,
                    $fileName,
                    $rowNumber,
                    3,
                    $rowData[3],
                    "El total de registros ($expectedCount) no coincide con las filas encontradas ($actualCount) en el archivo " . $fileToFind . '. Ajuste el valor o el archivo.'
                );
                $isValid = false;
            }
        }

        return $isValid;
    }

    /**
     * Verifica si una fecha en formato dd/mm/aaaa es válida.
     */
    private static function isValidDate(string $date): bool
    {
        $parts = explode('/', $date);
        if (count($parts) !== 3) return false;
        return checkdate((int) $parts[1], (int) $parts[0], (int) $parts[2]);
    }

    /**
     * Verifica si una fecha es posterior a la actual.
     */
    private static function isDateAfterToday(string $date): bool
    {
        $dateTime = \DateTime::createFromFormat('d/m/Y', $date);
        return $dateTime > new \DateTime('today');
    }

    /**
     * Cuenta las filas de un archivo basado en su código.
     */
    private static function countFileRows(string $tempDir, string $codigoArchivo): ?int
    {
        $filePath = "$tempDir/$codigoArchivo"; // Busca archivo con ese código (ej. AF123.txt)
        if (empty($filePath)) return null;

        $handle = fopen($filePath, 'r');
        if (!$handle) return null;

        $count = 0;
        while (fgetcsv($handle, 0, ',') !== false) {
            $count++;
        }

        fclose($handle);
        return $count;
    }
}
