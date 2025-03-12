<?php

namespace App\Helpers\FilingOld;

use App\Helpers\Common\ErrorCollector;

class AFFileValidator
{
    /**
     * Valida el archivo CT y sus columnas.
     *
     * @param string $filePath Ruta del archivo CT
     * @param string $tempDir Directorio temporal donde están los archivos extraídos del ZIP
     * @return bool Verdadero si pasa todas las validaciones, falso si hay errores
     */
    public static function validate(string $fileName, string $rowData, $rowNumber)
    {

        logMessage($fileName);

        $rowData = explode(",", $rowData);

        // $isValid = true;

        // // Validar cada fila
        // $codigoArchivos = []; // Para rastrear duplicados
        // foreach ($chunk as $key =>  $rowData) {

        // 1. Validar codigo_prestador (columna 1)
        if (!ctype_digit($rowData[0])) {
            ErrorCollector::addError(
                'FILE_CT_ERROR_001',
                'R',
                null,
                $fileName,
                $rowNumber,
                "Código del prestador de servicios de salud",
                $rowData[0],
                'El código del prestador de servicios de salud debe ser numérico. Corrija el valor a solo dígitos.'
            );
            $isValid = false;
        }
        if (strlen($rowData['codigo_prestador']) !== 12) {
            ErrorCollector::addError(
                'FILE_CT_ERROR_002',
                'R',
                null,
                $fileName,
                $rowNumber,
                'codigo_prestador',
                $rowData['codigo_prestador'],
                'El código del prestador debe tener exactamente 12 dígitos. Ajuste la longitud.'
            );
            $isValid = false;
        }

        //     // 2. Validar fecha (columna 2)
        //     if (!preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $rowData['fecha']) || !self::isValidDate($rowData['fecha'])) {
        //         ErrorCollector::addError(
        //             'FILE_CT_ERROR_006',
        //             'R',
        //             null,
        //             $fileName,
        //             $rowNumber,
        //             'fecha',
        //             $rowData['fecha'],
        //             'La fecha debe estar en formato dd/mm/aaaa. Corrija el formato.'
        //         );
        //         $isValid = false;
        //     } elseif (self::isDateAfterToday($rowData['fecha'])) {
        //         ErrorCollector::addError(
        //             'FILE_CT_ERROR_007',
        //             'R',
        //             null,
        //             $fileName,
        //             $rowNumber,
        //             'fecha',
        //             $rowData['fecha'],
        //             'La fecha no puede ser mayor a la actual. Use una fecha válida anterior a hoy.'
        //         );
        //         $isValid = false;
        //     }

        //     // 3. Validar codigo_archivo (columna 3)
        //     $prefix = strtoupper(substr($rowData['codigo_archivo'], 0, 2));
        //     $allowedPrefixes = ["AC", "AF", "AH", "AM", "AN", "AP", "AT", "AU", "US", "CT"];
        //     if (!in_array($prefix, $allowedPrefixes)) {
        //         ErrorCollector::addError(
        //             'FILE_CT_ERROR_008',
        //             'R',
        //             null,
        //             $fileName,
        //             $rowNumber,
        //             'codigo_archivo',
        //             $rowData['codigo_archivo'],
        //             'El código del archivo debe iniciar con AC, AF, AH, AM, AN, AP, AT, AU, US o CT. Corrija el prefijo.'
        //         );
        //         $isValid = false;
        //     }
        //     if (in_array($rowData['codigo_archivo'], $codigoArchivos)) {
        //         ErrorCollector::addError(
        //             'FILE_CT_ERROR_009',
        //             'R',
        //             null,
        //             $fileName,
        //             $rowNumber,
        //             'codigo_archivo',
        //             $rowData['codigo_archivo'],
        //             'El código del archivo está repetido en el CT. Asegúrese de que cada código sea único.'
        //         );
        //         $isValid = false;
        //     } else {
        //         $codigoArchivos[] = $rowData['codigo_archivo'];
        //     }

        //     // 4. Validar total_registros (columna 4)
        //     if (!ctype_digit($rowData['total_registros']) || strpos($rowData['total_registros'], '.') !== false) {
        //         ErrorCollector::addError(
        //             'FILE_CT_ERROR_0010',
        //             'R',
        //             null,
        //             $fileName,
        //             $rowNumber,
        //             'total_registros',
        //             $rowData['total_registros'],
        //             'El total de registros debe ser un número entero. Corrija el valor.'
        //         );
        //         $isValid = false;
        //     } else {
        //         $expectedCount = (int) $rowData['total_registros'];
        //         $actualCount = self::countFileRows($tempDir, $rowData['codigo_archivo']);
        //         if ($actualCount === null) {
        //             ErrorCollector::addError(
        //                 'FILE_CT_ERROR_0011',
        //                 'R',
        //                 null,
        //                 $fileName,
        //                 $rowNumber,
        //                 'total_registros',
        //                 $rowData['codigo_archivo'],
        //                 'No se encontró el archivo correspondiente al código ' . $rowData['codigo_archivo'] . '. Verifique que exista en el ZIP.'
        //             );
        //             $isValid = false;
        //         } elseif ($actualCount !== $expectedCount) {
        //             ErrorCollector::addError(
        //                 'FILE_CT_ERROR_0012',
        //                 'R',
        //                 null,
        //                 $fileName,
        //                 $rowNumber,
        //                 'total_registros',
        //                 $rowData['total_registros'],
        //                 "El total de registros ($expectedCount) no coincide con las filas encontradas ($actualCount) en el archivo " . $rowData['codigo_archivo'] . '. Ajuste el valor o el archivo.'
        //             );
        //             $isValid = false;
        //         }
        //     }
        // }

        // return $isValid;
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
        $filePath = glob("$tempDir/$codigoArchivo*"); // Busca archivo con ese código (ej. AF123.txt)
        if (empty($filePath)) return null;

        $handle = fopen($filePath[0], 'r');
        if (!$handle) return null;

        $count = 0;
        while (fgetcsv($handle, 0, ',') !== false) {
            $count++;
        }
        fclose($handle);
        return $count;
    }
}
