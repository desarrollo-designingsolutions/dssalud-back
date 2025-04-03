<?php

namespace App\Helpers\FilingOld;

use App\Helpers\Common\ErrorCollector;

class USFileValidator
{
    /**
     * Valida el archivo CT y sus columnas.
     *
     * @param  string  $fileName  Nombre del archivo
     * @param  string  $rowData  Datos de la fila del txt a validar (como cadena CSV)
     * @param  int  $rowNumber  Número de la fila del txt a validar
     * @param  string  $filing_id  ID del proceso
     * @return bool
     */
    public static function validate(string $fileName, string $rowData, int $rowNumber, string $filing_id): void
    {
        $keyErrorRedis = "filingOld:{$filing_id}:errors";

        // Dividir la fila en columnas
        $rowData = array_map('trim', explode(',', $rowData));

        $titleColumn = [
            'Columna 1: Tipo de identificación del usuario.',
            'Columna 2: Número de identificación del usuario del sistema.',
            'Columna 3: Código entidad administradora.',
            'Columna 4: Tipo de usuario.',
            'Columna 5: Primer apellido del usuario.',
            'Columna 6: Segundo apellido del usuario.',
            'Columna 7: Primer nombre del usuario.',
            'Columna 8: Segundo nombre del usuario.',
            'Columna 9: Edad.',
            'Columna 10: Unidad de medida de la edad.',
            'Columna 11: Sexo.',
            'Columna 12: Código del departamento de residencia habitual.',
            'Columna 13: Código del municipio de residencia habitual.',
            'Columna 14: Zona de residencia habitual.',
        ];

        // Validar columna 0: Tipo de identificación del usuario
        $allowedTypes = ['CC', 'CE', 'CD', 'PA', 'SC', 'PE', 'RE', 'RC', 'TI', 'CN', 'AS', 'MS', 'DE', 'PT', 'SI'];
        $typeId = trim($rowData[0] ?? '');
        $idNumber = trim($rowData[1] ?? ''); // Columna 2: Número de identificación
        $ageUnit = trim($rowData[9] ?? ''); // Columna 10: Unidad de medida

        // Verificar si el tipo de identificación es válido
        if (! in_array($typeId, $allowedTypes)) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_US_ERROR_001',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[0],
                $typeId,
                'El dato registrado no es un valor permitido.'
            );
        } else {
            switch ($typeId) {
                case 'CC':
                    // 1. Validar que el número de identificación sea numérico
                    if (! empty($idNumber) && ! ctype_digit($idNumber)) {
                        ErrorCollector::addError(
                            $keyErrorRedis,
                            'FILE_US_ERROR_002',
                            'R',
                            null,
                            $fileName,
                            $rowNumber,
                            $titleColumn[0],
                            $rowData[0],
                            'El campo número de identificación debe ser numérico.'
                        );
                    }

                    // 2. Validar que la unidad de medida sea igual a 1
                    if ($ageUnit !== '1') {
                        ErrorCollector::addError(
                            $keyErrorRedis,
                            'FILE_US_ERROR_003',
                            'R',
                            null,
                            $fileName,
                            $rowNumber,
                            $titleColumn[0],
                            $rowData[0],
                            'El campo unidad de medida es diferente a 1.'
                        );
                    }
                    break;
                case 'CE':
                    // 1. Validar que la unidad de medida sea igual a 1
                    if ($ageUnit !== '1') {
                        ErrorCollector::addError(
                            $keyErrorRedis,
                            'FILE_US_ERROR_004',
                            'R',
                            null,
                            $fileName,
                            $rowNumber,
                            $titleColumn[0],
                            $rowData[0],
                            'El campo unidad de medida es diferente a 1.'
                        );
                    }
                    break;
                case 'TI':
                    // 1. Validar que el número de identificación sea numérico
                    if (! empty($idNumber) && ! ctype_digit($idNumber)) {
                        ErrorCollector::addError(
                            $keyErrorRedis,
                            'FILE_US_ERROR_005',
                            'R',
                            null,
                            $fileName,
                            $rowNumber,
                            $titleColumn[0],
                            $rowData[0],
                            'El campo número de identificación debe ser numérico.'
                        );
                    }

                    // 2. Validar que la unidad de medida sea igual a 1
                    if ($ageUnit !== '1') {
                        ErrorCollector::addError(
                            $keyErrorRedis,
                            'FILE_US_ERROR_006',
                            'R',
                            null,
                            $fileName,
                            $rowNumber,
                            $titleColumn[0],
                            $rowData[0],
                            'El campo unidad de medida es diferente a 1.'
                        );
                    }
                    break;
                case 'CN':
                    // 1. Validar que la unidad de medida sea igual a 3
                    if ($ageUnit !== '3') {
                        ErrorCollector::addError(
                            $keyErrorRedis,
                            'FILE_US_ERROR_022',
                            'R',
                            null,
                            $fileName,
                            $rowNumber,
                            $titleColumn[0],
                            $rowData[0],
                            'El campo unidad de medida es diferente a 3.'
                        );
                    }
                    break;
                case 'AS':
                    // 1. Validar que la unidad de medida sea igual a 1
                    if ($ageUnit !== '1') {
                        ErrorCollector::addError(
                            $keyErrorRedis,
                            'FILE_US_ERROR_007',
                            'R',
                            null,
                            $fileName,
                            $rowNumber,
                            $titleColumn[0],
                            $rowData[0],
                            'El campo unidad de medida es diferente a 1.'
                        );
                    }
                    break;
            }
        }

        // Validar Número de identificación del usuario del sistema
        if ($rowData[0] == 'CC' && ! ctype_digit($rowData[1])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_US_ERROR_008',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[1],
                $rowData[1],
                'El dato registrado no es un valor numerico.'
            );
        }

        if ($rowData[0] == 'CC' && strlen($rowData[1]) > 10) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_US_ERROR_009',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[1],
                $rowData[1],
                'El dato registrado contiene mas de 10 caracteres.'
            );
        }

        if ($rowData[0] == 'CE' && strlen($rowData[1]) > 6) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_US_ERROR_010',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[1],
                $rowData[1],
                'El dato registrado contiene mas de 6 caracteres.'
            );
        }

        if ($rowData[0] == 'CD' && strlen($rowData[1]) > 16) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_US_ERROR_011',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[1],
                $rowData[1],
                'El dato registrado contiene mas de 16 caracteres.'
            );
        }
        if ($rowData[0] == 'PA' && strlen($rowData[1]) > 16) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_US_ERROR_012',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[1],
                $rowData[1],
                'El dato registrado contiene mas de 16 caracteres.'
            );
        }
        if ($rowData[0] == 'SC' && strlen($rowData[1]) > 16) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_US_ERROR_013',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[1],
                $rowData[1],
                'El dato registrado contiene mas de 16 caracteres.'
            );
        }
        if ($rowData[0] == 'PE' && strlen($rowData[1]) > 15) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_US_ERROR_014',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[1],
                $rowData[1],
                'El dato registrado contiene mas de 15 caracteres.'
            );
        }
        if ($rowData[0] == 'RE' && strlen($rowData[1]) > 15) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_US_ERROR_015',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[1],
                $rowData[1],
                'El dato registrado contiene mas de 15 caracteres.'
            );
        }
        if ($rowData[0] == 'RC' && strlen($rowData[1]) > 11) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_US_ERROR_016',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[1],
                $rowData[1],
                'El dato registrado contiene mas de 11 caracteres.'
            );
        }

        if ($rowData[0] == 'TI' && ! ctype_digit($rowData[1])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_US_ERROR_017',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[1],
                $rowData[1],
                'El dato registrado no es un valor numerico.'
            );
        }

        if ($rowData[0] == 'TI' && strlen($rowData[1]) > 11) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_US_ERROR_018',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[1],
                $rowData[1],
                'El dato registrado contiene mas de 11 caracteres.'
            );
        }
        if ($rowData[0] == 'CN' && strlen($rowData[1]) > 9) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_US_ERROR_019',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[1],
                $rowData[1],
                'El dato registrado contiene mas de 9 caracteres.'
            );
        }

        if ($rowData[0] == 'AS' && strlen($rowData[1]) > 10) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_US_ERROR_020',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[1],
                $rowData[1],
                'El dato registrado contiene mas de 10 caracteres.'
            );
        }
        if ($rowData[0] == 'MS' && strlen($rowData[1]) > 12) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_US_ERROR_021',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[1],
                $rowData[1],
                'El dato registrado contiene mas de 12 caracteres.'
            );
        }

        // validar Tipo de usuario
        $allowedTypes = [1, 2, 3, 4, 5, 6, 7, 8];
        if (! in_array($rowData[3], $allowedTypes)) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_US_ERROR_023',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[3],
                $rowData[3],
                'El dato registrado no es un valor permitido.'
            );
        }

        // Validar Primer apellido del usuario
        if (empty($rowData[4])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_US_ERROR_024',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[4],
                $rowData[4],
                'El primer apellido es un dato obligatorio.'
            );
        }

        // validar Primer nombre del usuario
        if (empty($rowData[6])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_US_ERROR_025',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[6],
                $rowData[6],
                'El primer apellido es un dato obligatorio.'
            );
        }

        // validar Edad
        if (empty($rowData[8])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_US_ERROR_026',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[8],
                $rowData[8],
                'El dato edad es obligatorio.'
            );
        }

        // validar Unidad de medida de la edad
        $allowedTypes = [1, 2, 3];
        if (! in_array($rowData[9], $allowedTypes)) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_US_ERROR_028',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[9],
                $rowData[9],
                'El dato edad es obligatorio.'
            );
        }

        // validar Unidad de medida de la edad
        if (empty($rowData[9])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_US_ERROR_029',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[9],
                $rowData[9],
                'El registro del dato es obligatorio.'
            );
        }

        // validar Sexo
        if (empty($rowData[10])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_US_ERROR_030',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[10],
                $rowData[10],
                'El registro del dato es obligatorio.'
            );
        }
        $allowedTypes = ['M', 'F'];
        if (! in_array($rowData[10], $allowedTypes)) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_US_ERROR_034',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[10],
                $rowData[10],
                'El registro del dato es obligatorio.'
            );
        }

        // validar Código del departamento de residencia habitual
        if (empty($rowData[11])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_US_ERROR_032',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[11],
                $rowData[11],
                'El registro del dato es obligatorio.'
            );
        }
        // validar Código del municipio de residencia habitual
        if (empty($rowData[12])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_US_ERROR_033',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[12],
                $rowData[12],
                'El registro del dato es obligatorio.'
            );
        }
        // validar Zona de residencia habitual
        $allowedTypes = ['U', 'R'];
        if (! in_array($rowData[13], $allowedTypes)) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_US_ERROR_033',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[13],
                $rowData[13],
                'El dato registrado no es un valor permitido.'
            );
        }

        // validar Código del municipio de residencia habitual
        if (empty($rowData[13])) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'FILE_US_ERROR_033',
                'R',
                null,
                $fileName,
                $rowNumber,
                $titleColumn[13],
                $rowData[13],
                'El registro del dato es obligatorio.'
            );
        }

        // logMessage(ErrorCollector::getErrors($keyErrorRedis));
    }
}
