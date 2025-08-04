<?php

namespace App\Services\Conciliation;

use Maatwebsite\Excel\Facades\Excel;

class ExcelStructureValidator
{
    public function __construct() {}

    public function validate($filePath)
    {
        $expectedHeaders = [
            'ID', // obligatorio
            'FACTURA_ID', // obligatorio
            'SERVICIO_ID',
            'ORIGIN',
            'NIT',
            'RAZON_SOCIAL',
            'NUMERO_FACTURA',
            'FECHA_INICIO',
            'FECHA_FIN',
            'MODALIDAD',
            'REGIMEN',
            'COBERTURA',
            'CONTRATO',
            'TIPO_DOCUMENTO',
            'NUMERO_DOCUMENTO',
            'PRIMER_NOMBRE',
            'SEGUNDO_NOMBRE',
            'PRIMER_APELLIDO',
            'SEGUNDO_APELLIDO',
            'GENERO',
            'CODIGO_SERVICIO',
            'DESCRIPCION_SERVICIO',
            'CANTIDAD_SERVICIO',
            'VALOR_UNITARIO_SERVICIO',
            'VALOR_TOTAL_SERVICIO',
            'CODIGOS_GLOSA',
            'OBSERVACIONES_GLOSAS',
            'VALOR_GLOSA',
            'VALOR_APROBADO',
            'ESTADO_RESPUESTA', // obligatorio
            'NUMERO_DE_AUTORIZACION',
            'VALOR_ACEPTADO_IPS', // obligatorio
            'VALOR_ACEPTADO_EPS', // obligatorio
            'VALOR_RATIFICADO_EPS', // obligatorio
            'OBSERVACIONES', // obligatorio
        ];

        try {
            $sheets = Excel::toArray([], $filePath, null, \Maatwebsite\Excel\Excel::XLSX);

            $validationResults = [];
            $operationFailed = false;

            foreach ($sheets as $index => $sheet) {
                $sheetName = 'Hoja '.($index + 1);

                $headers = array_map('strtoupper', $sheet[0]);
                $headers = array_slice($headers, 0, count($expectedHeaders));

                $errors = [];
                // Validación SOLO de encabezados fijos
                for ($i = 0; $i < count($expectedHeaders); $i++) {
                    if (! isset($headers[$i]) || $headers[$i] !== $expectedHeaders[$i]) {
                        if (! isset($headers[$i])) {
                            $errors[] = "Falta el encabezado esperado '{$expectedHeaders[$i]}' en la posición ".($i + 1);
                        } else {
                            $errors[] = "El encabezado '{$headers[$i]}' en la posición ".($i + 1)." no coincide con '{$expectedHeaders[$i]}' esperado";
                        }
                    }
                }

                // Solo se validan los encabezados básicos

                if (! empty($errors)) {
                    $operationFailed = true;
                }

                $validationResults[$sheetName] = [
                    'valid' => empty($errors),
                    'errors' => $errors,
                ];
            }

            return [
                'operation_failed' => $operationFailed,
                'data' => $validationResults,
            ];
        } catch (\Exception $e) {
            throw new \Exception('Error al procesar el archivo: '.$e->getMessage());
        }
    }
}
