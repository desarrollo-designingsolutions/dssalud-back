<?php

namespace App\Services;

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
        'VALOR_ACEPTADO_IPS',
        'VALOR_ACEPTADO_EPS',
        'VALOR_RATIFICADO_EPS',
        'OBSERVACIONES',
    ];

    protected string $batchId;
    protected int $totalRows = 0; // Añadido para el progreso
    protected $eventDispatcher; // Callback para despachar eventos

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

    public function validateCsv(string $filePath): array
    {
        // Asegurar que el prefijo se use también en la limpieza inicial
        $cachePrefix = config('database.redis.options.prefix', '');

        Redis::del("import_errors:{$this->batchId}"); // Esto ya se prefija automáticamente

        // Limpiar las claves de recolección de factura para este batch antes de empezar
        $keysToClean = Redis::keys($cachePrefix . "csv_factura_total_counts:{$this->batchId}");
        $keysToClean = array_merge($keysToClean, Redis::keys($cachePrefix . "csv_factura_rows:{$this->batchId}:*"));
        $keysToClean = array_merge($keysToClean, Redis::keys($cachePrefix . "csv_unique_factura_ids:{$this->batchId}")); // Asegurar que esta también se limpie

        if (!empty($keysToClean)) {
            Log::info(sprintf("DEBUG VALIDATION: Limpiando %d claves de Redis al inicio de la validación.", count($keysToClean)));
            Redis::del($keysToClean);
        } else {
            Log::info("DEBUG VALIDATION: No se encontraron claves de Redis para limpiar al inicio de la validación.");
        }

        $this->validateHeaders($filePath);

        if (Redis::llen("import_errors:{$this->batchId}") > 0) {
            Log::warning("Header validation failed for batch ID: {$this->batchId}. Stopping further validation.");
            // Disparar evento de finalización con errores si la validación de cabecera falla
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
        fclose($handle);

        if ($headers && !empty($headers[0])) {
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        }

        Log::info('Headers read from CSV:', [$headers]);
        Log::info('Expected headers:', [$this->requiredHeaders]);

        if ($headers === false || count($headers) !== count($this->requiredHeaders)) {
            $this->addError(0, 'headers', 'Invalid number of headers or file is empty.', 'header_mismatch', strval(count($headers)), json_encode($headers));
            return;
        }

        foreach ($this->requiredHeaders as $index => $expectedHeader) {
            if (!isset($headers[$index]) || trim($headers[$index]) !== $expectedHeader) {
                $this->addError(0, 'headers', "Expected header '$expectedHeader' at position " . ($index + 1) . ", found '" . ($headers[$index] ?? 'N/A') . "'", 'header_mismatch', $headers[$index] ?? '', json_encode($headers));
            }
        }
    }

    protected function validateRows(string $filePath): void
    {
        $rowNumber = 1;
        $processedRows = 0;
        $dispatchInterval = max(1, floor($this->totalRows / 100)); // Despachar al menos 100 veces por archivo, mínimo 1 por cada fila

        LazyCollection::make(function () use ($filePath) {
            $handle = fopen($filePath, 'r');
            fgetcsv($handle, 0, ';'); // Saltar cabecera
            while (($row = fgetcsv($handle, 0, ';')) !== false) {
                yield $row;
            }
            fclose($handle);
        })->each(function ($row) use (&$rowNumber, &$processedRows, $dispatchInterval) {
            $rowNumber++;
            $processedRows++;

            if (count($row) !== count($this->requiredHeaders)) {
                $this->addError($rowNumber, 'row_structure', 'Row has an incorrect number of columns.', 'column_count_mismatch', count($row), json_encode($row));
                return;
            }

            $data = array_combine($this->requiredHeaders, $row);

            // Recolección de datos para la validación de "Facturas Completas"
            $facturaId = trim($data['FACTURA_ID'] ?? '');
            $auditoryReportId = trim($data['ID'] ?? '');

            if (!empty($facturaId) && !empty($auditoryReportId)) {
                Redis::hincrby("csv_factura_total_counts:{$this->batchId}", $facturaId, 1);
                Redis::rpush("csv_factura_rows:{$this->batchId}:{$facturaId}", $rowNumber);
                Redis::sadd("csv_unique_factura_ids:{$this->batchId}", $facturaId);

                // DEBUG: Verificar la creación de una clave de conteo de factura
                if ($processedRows === 1) { // Solo para la primera fila para evitar logs excesivos
                    $cachePrefix = config('database.redis.options.prefix', '');
                    $prefixedKey = $cachePrefix . "csv_factura_total_counts:{$this->batchId}";
                    Log::info("DEBUG VALIDATION: Clave de conteo de factura creada: {$prefixedKey}. Valor: " . Redis::hget("csv_factura_total_counts:{$this->batchId}", $facturaId));
                }
            }

            // 1. Validación de campos obligatorios
            $requiredFields = [
                'ID',
                'FACTURA_ID',
                'SERVICIO_ID',
                'ESTADO_RESPUESTA',
                'VALOR_ACEPTADO_IPS',
                'VALOR_ACEPTADO_EPS',
                'VALOR_RATIFICADO_EPS',
                'OBSERVACIONES',
            ];

            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || trim($data[$field]) === '') {
                    $this->addError($rowNumber, $field, "El campo '$field' es obligatorio", 'missing_field', $data[$field] ?? '', json_encode($data));
                }
            }

            // 2. Validación para ESTADO_RESPUESTA
            $validStatuses = [
                'Glosa aceptada por IPS',
                'Glosa No aceptada por IPS (Genera respuesta)',
                'Glosa Subsanada por IPS (Genera respuesta y Envía soporte)',
                'Glosa para conciliación',
            ];
            if (!in_array($data['ESTADO_RESPUESTA'], $validStatuses)) {
                $this->addError($rowNumber, 'ESTADO_RESPUESTA', 'Invalid response status', 'invalid_value', $data['ESTADO_RESPUESTA'], json_encode($data));
            }

            // 3. Validación de campos numéricos y positivos
            $numericPositiveFields = [
                'VALOR_ACEPTADO_IPS',
                'VALOR_ACEPTADO_EPS',
                'VALOR_RATIFICADO_EPS',
            ];

            foreach ($numericPositiveFields as $field) {
                if (!isset($data[$field]) || trim($data[$field]) === '') {
                    continue;
                }

                $value = str_replace(',', '.', $data[$field]);

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
                    $sumAcceptedValues = (float)str_replace(',', '.', $data['VALOR_ACEPTADO_IPS']) +
                                         (float)str_replace(',', '.', $data['VALOR_ACEPTADO_EPS']) +
                                         (float)str_replace(',', '.', $data['VALOR_RATIFICADO_EPS']);

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

            // Despachar evento de progreso periódicamente
            if ($processedRows % $dispatchInterval === 0 || $processedRows === $this->totalRows) {
                if ($this->eventDispatcher) {
                    ($this->eventDispatcher)($processedRows, 'Validando filas CSV', 'active', (string)$rowNumber);
                }
            }
        });

        // Despachar evento final después de la validación de filas (si no se hizo en el bucle)
        if ($this->eventDispatcher && ($processedRows % $dispatchInterval !== 0 || $processedRows > 0)) {
             ($this->eventDispatcher)($processedRows, 'Validación de filas CSV completada', 'active', (string)$rowNumber);
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
