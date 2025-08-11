<?php

namespace App\Imports\ConciliationImport\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;

class CsvValidationService
{
    protected array $requiredHeaders = [
        'ID',
        'FACTURA_ID', // Este es el NUMERO_FACTURA en el CSV
        'SERVICIO_ID',
        'ORIGIN',
        'NIT',
        'RAZON_SOCIAL',
        'NUMERO_FACTURA', // Este es el NUMERO_FACTURA en el CSV
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
        'ESTADO_RESPUESTA',
        'NUMERO_DE_AUTORIZACION',
        'RESPUESTA_DE_IPS',
        'VALOR_ACEPTADO_POR_IPS',
        'VALOR_ACEPTADO_POR_EPS',
        'VALOR_RATIFICADO_EPS',
        'OBSERVACIONES',
    ];

    protected string $batchId;
    protected int $totalRows = 0;
    protected $eventDispatcher;

    public function __construct(string $batchId)
    {
        $this->batchId = $batchId;
    }

    /**
     * Establece el número total de filas para el cálculo de progreso.
     */
    public function setTotalRows(int $totalRows): void
    {
        $this->totalRows = $totalRows;
    }

    /**
     * Establece el callback para despachar eventos de progreso.
     */
    public function setEventDispatcher(callable $eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Nuevo método para obtener los encabezados requeridos.
     */
    public function getRequiredHeaders(): array
    {
        return $this->requiredHeaders;
    }

    public function validateCsv(string $filePath): array
    {
        $cachePrefix = config('database.redis.options.prefix', '');

        Redis::del("import_errors:{$this->batchId}");

        $keysToClean = Redis::keys($cachePrefix . "csv_factura_total_counts:{$this->batchId}");
        $keysToClean = array_merge($keysToClean, Redis::keys($cachePrefix . "csv_factura_rows:{$this->batchId}:*"));
        $keysToClean = array_merge($keysToClean, Redis::keys($cachePrefix . "csv_unique_factura_ids:{$this->batchId}"));

        if (!empty($keysToClean)) {
            Log::info(sprintf("DEBUG VALIDATION: Limpiando %d claves de Redis al inicio de la validación.", count($keysToClean)));
            Redis::del($keysToClean);
        } else {
            Log::info("DEBUG VALIDATION: No se encontraron claves de Redis para limpiar al inicio de la validación.");
        }

        $this->validateHeaders($filePath);

        if (Redis::llen("import_errors:{$this->batchId}") > 0) {
            Log::warning("Header validation failed for batch ID: {$this->batchId}. Stopping further validation.");
            if ($this->eventDispatcher) {
                ($this->eventDispatcher)(0, 'Error en cabeceras CSV', 'failed', '0');
            }
            return $this->getErrors();
        }

        Log::info("Header validation passed for batch ID: {$this->batchId}. Proceeding with row validation.");
        $this->validateRows($filePath);

        return $this->getErrors();
    }

    protected function validateHeaders(string $filePath): void
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            $this->addError(0, 'file', 'Could not open CSV file.', 'file_error', $filePath, '');
            return;
        }

        $headers = fgetcsv($handle, 0, ';');
        if ($headers && !empty($headers[0])) {
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        }
        fclose($handle); // Cerrar el archivo después de leer los encabezados

        Log::info('Headers read from CSV:', [$headers]);
        Log::info('Expected headers (exact match):', [$this->requiredHeaders]);

        // Comparar las cabeceras leídas directamente con las cabeceras requeridas (sensible a la capitalización)
        $missingHeaders = array_diff($this->requiredHeaders, $headers);

        if (!empty($missingHeaders)) {
            foreach ($missingHeaders as $missingHeader) {
                $this->addError(
                    0, // Fila 0 para errores de cabecera
                    'headers',
                    "Expected header '$missingHeader' not found in file (exact match required).",
                    'header_missing',
                    'N/A',
                    json_encode($headers)
                );
            }
        }

        // Verificar si el número total de cabeceras coincide
        if (count($headers) !== count($this->requiredHeaders)) {
            $this->addError(
                0,
                'headers',
                sprintf("Number of headers mismatch. Expected %d, found %d.", count($this->requiredHeaders), count($headers)),
                'header_count_mismatch',
                strval(count($headers)),
                json_encode($headers)
            );
        }
    }

    protected function validateRows(string $filePath): void
    {
        $rowNumber = 1;
        $processedRows = 0;
        $dispatchInterval = max(1, floor($this->totalRows / 100));

        LazyCollection::make(function () use ($filePath) {
            $handle = fopen($filePath, 'r');
            $actualHeaders = fgetcsv($handle, 0, ';'); // Leer los encabezados para mapeo
            if ($actualHeaders && !empty($actualHeaders[0])) {
                $actualHeaders[0] = preg_replace('/^\xEF\xBB\xBF/', '', $actualHeaders[0]);
            }
            // Crear un mapeo de encabezados a sus índices originales (sensible a la capitalización)
            $headerMap = array_flip($actualHeaders);

            while (($row = fgetcsv($handle, 0, ';')) !== false) {
                yield ['row_data' => $row, 'header_map' => $headerMap];
            }
            fclose($handle);
        })->each(function ($item) use (&$rowNumber, &$processedRows, $dispatchInterval) {
            $row = $item['row_data'];
            $headerMap = $item['header_map'];

            $rowNumber++;
            $processedRows++;

            // Crear un array de datos usando los requiredHeaders como claves y los valores de la fila
            $data = [];
            foreach ($this->requiredHeaders as $requiredHeader) {
                $columnIndex = $headerMap[$requiredHeader] ?? null; // Buscar el índice exacto

                if ($columnIndex !== null && isset($row[$columnIndex])) {
                    $data[$requiredHeader] = $row[$columnIndex];
                } else {
                    $data[$requiredHeader] = ''; // Si no se encuentra, asignar vacío
                }
            }

            // Recolección de datos para la validación de "Facturas Completas"
            $facturaId = trim($data['FACTURA_ID'] ?? '');
            $auditoryReportId = trim($data['ID'] ?? '');

            if (!empty($facturaId) && !empty($auditoryReportId)) {
                Redis::hincrby("csv_factura_total_counts:{$this->batchId}", $facturaId, 1);
                Redis::rpush("csv_factura_rows:{$this->batchId}:{$facturaId}", $rowNumber);
                Redis::sadd("csv_unique_factura_ids:{$this->batchId}", $facturaId);
            }

            // 1. Validación de campos obligatorios
            $requiredFields = [
                'ID',
                'FACTURA_ID',
                'SERVICIO_ID',
                'ESTADO_RESPUESTA',
                'VALOR_ACEPTADO_POR_IPS',
                'VALOR_ACEPTADO_POR_EPS',
                'VALOR_RATIFICADO_EPS',
                'OBSERVACIONES',
            ];

            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || trim($data[$field]) === '') {
                    $this->addError($rowNumber, $field, "El campo '$field' es obligatorio", 'missing_field', $data[$field] ?? '', json_encode($data));
                }
            }

            // 2. Validación para ESTADO_RESPUESTA (sensible a la capitalización)
            $validStatuses = [
                'Glosa aceptada por IPS',
                'Glosa No aceptada por IPS (Genera respuesta)',
                'Glosa Subsanada por IPS (Genera respuesta y Envía soporte)',
                'Glosa para conciliación',
            ];
            // La comparación es directa, sin mb_strtoupper, para mantener la sensibilidad a la capitalización
            if (!in_array(trim($data['ESTADO_RESPUESTA'] ?? ''), $validStatuses)) {
                $this->addError($rowNumber, 'ESTADO_RESPUESTA', 'Invalid response status (exact match required)', 'invalid_value', $data['ESTADO_RESPUESTA'] ?? '', json_encode($data));
            }

            // 3. Validación de campos numéricos y positivos
            $numericPositiveFields = [
                'VALOR_ACEPTADO_POR_IPS',
                'VALOR_ACEPTADO_POR_EPS',
                'VALOR_RATIFICADO_EPS',
            ];

            foreach ($numericPositiveFields as $field) {
                if (!isset($data[$field]) || trim($data[$field]) === '') {
                    continue;
                }

                $value = str_replace(',', '.', trim($data[$field]));

                if (!is_numeric($value)) {
                    $this->addError($rowNumber, $field, "El campo '$field' debe ser un valor numérico", 'invalid_numeric', $data[$field], json_encode($data));
                } elseif ((float)$value < 0) {
                    $this->addError($rowNumber, $field, "El campo '$field' debe ser un valor numérico positivo", 'negative_value', $data[$field], json_encode($data));
                }
            }

            // 4. Validación cruzada de montos (usando Redis con clave específica del batch)
            if (isset($data['ID']) && !empty(trim($data['ID']))) {
                $auditoryReportId = trim($data['ID']);
                $redisKey = "auditory_glosa:{$this->batchId}:{$auditoryReportId}";
                $expectedValorGlosa = Redis::get($redisKey);

                if (is_null($expectedValorGlosa)) {
                    $this->addError($rowNumber, 'ID', "ID '$auditoryReportId' no encontrado en auditory_final_reports o no precargado.", 'id_not_found', $data['ID'], json_encode($data));
                } else {
                    $valorAceptadoIps = (float)str_replace(',', '.', trim($data['VALOR_ACEPTADO_POR_IPS'] ?? '0'));
                    $valorAceptadoEps = (float)str_replace(',', '.', trim($data['VALOR_ACEPTADO_POR_EPS'] ?? '0'));
                    $valorRatificadoEps = (float)str_replace(',', '.', trim($data['VALOR_RATIFICADO_EPS'] ?? '0'));

                    $sumAcceptedValues = $valorAceptadoIps + $valorAceptadoEps + $valorRatificadoEps;

                    $expectedValorGlosaFloat = (float) $expectedValorGlosa;

                    if (abs($sumAcceptedValues - $expectedValorGlosaFloat) > 0.01) {
                        $this->addError($rowNumber, 'amounts', sprintf(
                            "La suma de valores aceptados (%.2f) no coincide con valor_glosa (%.2f) para ID '%s'",
                            $sumAcceptedValues,
                            $expectedValorGlosaFloat,
                            $auditoryReportId
                        ), 'amount_mismatch', strval($sumAcceptedValues), json_encode($data));
                    }
                }
            }

            if ($processedRows % $dispatchInterval === 0 || $processedRows === $this->totalRows) {
                if ($this->eventDispatcher) {
                    ($this->eventDispatcher)(
                        $processedRows, // Progreso principal basado en filas validadas
                        'Validando filas CSV',
                        'active',
                        sprintf('%d/%d filas validadas', $rowNumber, $this->totalRows) // Detalle en currentStudent
                    );
                }
            }
        });

        // Asegurar que el evento final de esta fase envía el 100% del progreso de validación de filas.
        if ($this->eventDispatcher && ($processedRows % $dispatchInterval !== 0 || $processedRows > 0)) {
            ($this->eventDispatcher)(
                $processedRows, // Progreso principal basado en filas validadas
                'Validación de filas CSV completada',
                'active',
                (string)$rowNumber // Detalle en currentStudent
            );
        }
    }

    public function addError(int $rowNumber, string $columnName, string $errorMessage, string $errorType, $errorValue, string $originalData): void
    {
        $error = [
            'id' => (string) Str::uuid(),
            'batch_id' => $this->batchId,
            'row_number' => $rowNumber,
            'column_name' => $columnName,
            'error_message' => $errorMessage,
            'error_type' => $errorType,
            'error_value' => strval($errorValue),
            'original_data' => $originalData ?: null,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];
        Redis::rpush("import_errors:{$this->batchId}", json_encode($error));
        Redis::expire("import_errors:{$this->batchId}", 3600);
    }

    public function getErrors(): array
    {
        $rawErrors = Redis::lrange("import_errors:{$this->batchId}", 0, -1);
        $errors = [];
        foreach ($rawErrors as $errorJson) {
            $errors[] = json_decode($errorJson, true);
        }
        return $errors;
    }
}
