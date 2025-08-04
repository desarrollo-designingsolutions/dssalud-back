<?php

namespace App\Services\Excel;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Imports\HeadingRowFormatter; // Asegúrate de importar Redis

class ExcelValidator implements ToCollection, WithHeadingRow, WithValidation
{
    private $config;

    private $errors = []; // Esto ya no se usa para acumular, solo para el retorno final

    private $headerRowValidated = false;

    private $rowsProcessed = 0;

    private $validRowsCount = 0;

    private $startTime;

    private $batchId; // Nueva propiedad para almacenar el batchId

    public function __construct(array $config = [])
    {
        $this->startTime = microtime(true);
        $this->config = array_merge([
            'validate_headers' => true,
            'validate_data' => true,
            'start_row' => 1,
            'max_rows' => null,
            'expected_headers' => [],
            'validation_rules' => [],
            'custom_messages' => [],
            'custom_validations' => [],
            'stop_on_first_error' => false,
            'sample_size' => null,
            'strict_header_order' => true,
            'case_sensitive_headers' => false,
            'trim_values' => true,
            'skip_empty_rows' => true,
            'batch_id' => null, // Inicializar batch_id
        ], $config);

        $this->batchId = $this->config['batch_id']; // Asignar el batchId
        HeadingRowFormatter::default('none');
    }

    public function startRow(): int
    {
        return $this->config['start_row'];
    }

    public function limit(): ?int
    {
        return $this->config['max_rows'];
    }

    public function chunkSize(): int
    {
        if (! $this->config['validate_data']) {
            return 1;
        }
        if ($this->config['max_rows'] && $this->config['max_rows'] > 10000) {
            return 200;
        }

        return $this->config['sample_size'] ?
            min(500, $this->config['sample_size']) : 500;
    }

    public function rules(): array
    {
        if (! $this->config['validate_data']) {
            return [];
        }
        $rules = $this->config['validation_rules'];
        if (! empty($this->config['custom_validations'])) {
            foreach ($this->config['custom_validations'] as $column => $columnRules) {
                if (! isset($rules[$column])) {
                    $rules[$column] = [];
                }
                $rules[$column] = array_merge((array) $rules[$column], $columnRules);
            }
        }

        return $rules;
    }

    public function customValidationMessages()
    {
        return $this->config['custom_messages'];
    }

    public function collection(Collection $rows)
    {
        if ($this->batchId === null) {
            Log::error('ExcelValidator: batchId no está configurado. Los errores no se guardarán en Redis.');
            // Considera lanzar una excepción o manejar esto de otra manera si batchId es mandatorio
        }

        if ($this->config['validate_headers'] && ! $this->headerRowValidated) {
            $headers = $rows->first() ? $rows->first()->keys()->toArray() : [];
            $this->validateHeaders($headers);
            $this->headerRowValidated = true;
        }

        if ($this->config['validate_data'] === false) {
            return;
        }

        foreach ($rows as $rowIndex => $row) {
            $this->rowsProcessed++;
            if ($this->config['skip_empty_rows'] && $this->isEmptyRow($row)) {
                continue;
            }
            if ($this->config['trim_values']) {
                $row = $row->map(fn ($value) => is_string($value) ? trim($value) : $value);
            }
            $this->performCustomRowValidations($row, $this->rowsProcessed + 1);

            if ($this->config['sample_size'] && $this->rowsProcessed >= $this->config['sample_size']) {
                break;
            }
            if ($this->config['stop_on_first_error'] && ! empty($this->errors)) {
                break;
            }
        }
    }

    private function validateHeaders(array $headers): void
    {
        $expected = $this->config['expected_headers'];
        if (empty($expected)) {
            return;
        }
        if (! $this->config['case_sensitive_headers']) {
            $headers = array_map('strtoupper', $headers);
            $expected = array_map('strtoupper', $expected);
        }
        if ($this->config['trim_values']) {
            $headers = array_map('trim', $headers);
            $expected = array_map('trim', $expected);
        }

        if (count($headers) !== count($expected)) {
            $this->addError(
                row: 1,
                column: 0,
                message: 'Número incorrecto de columnas. Esperadas: '.count($expected).', Encontradas: '.count($headers),
                type: 'header_count_mismatch',
                fullData: ['expected' => $expected, 'actual' => $headers]
            );
        }

        if ($this->config['strict_header_order']) {
            $this->validateHeadersStrict($headers, $expected);
        } else {
            $this->validateHeadersFlexible($headers, $expected);
        }
    }

    private function validateHeadersStrict(array $headers, array $expected): void
    {
        foreach ($expected as $index => $expectedHeader) {
            if (! isset($headers[$index])) {
                $this->addError(
                    row: 1,
                    column: $index + 1,
                    message: 'Falta columna en posición '.($index + 1).": {$expectedHeader}",
                    type: 'missing_header'
                );
            } elseif ($headers[$index] !== $expectedHeader) {
                $this->addError(
                    row: 1,
                    column: $index + 1,
                    message: 'Columna '.($index + 1)." incorrecta. Esperada: '{$expectedHeader}', Encontrada: '{$headers[$index]}'",
                    type: 'incorrect_header'
                );
            }
        }
    }

    private function validateHeadersFlexible(array $headers, array $expected): void
    {
        $missing = array_diff($expected, $headers);
        $extra = array_diff($headers, $expected);
        foreach ($missing as $missingHeader) {
            $this->addError(
                row: 1,
                column: array_search($missingHeader, $expected) + 1,
                message: "Falta header requerido: {$missingHeader}",
                type: 'missing_header'
            );
        }
        foreach ($extra as $extraHeader) {
            $this->addError(
                row: 1,
                column: array_search($extraHeader, $headers) + 1,
                message: "Header no esperado: {$extraHeader}",
                type: 'unexpected_header'
            );
        }
    }

    private function isEmptyRow(Collection $row): bool
    {
        return $row->filter(fn ($value) => ! empty(trim($value)))->isEmpty();
    }

    private function performCustomRowValidations(Collection $row, int $rowNumber): void
    {
        // Aquí puedes agregar validaciones personalizadas adicionales
    }

    public function onFailure($failures)
    {
        foreach ($failures as $failure) {
            $this->addError(
                row: $failure->row(),
                column: $this->getColumnNumber($failure->attribute()),
                message: implode(', ', $failure->errors()),
                type: 'data_validation',
                fullData: $failure->values()
            );
            if ($this->config['stop_on_first_error']) {
                break;
            }
        }
    }

    private function addError(int $row, int $column, string $message, string $type, array $fullData = []): void
    {
        $error = [
            'row_number' => $row, // Cambiado a row_number para consistencia con la tabla
            'column_name' => $this->getColumnName($column),
            'error_message' => $message,
            'error_type' => $type,
            'error_value' => $fullData[$column],
            'original_data' => $fullData,
            'timestamp' => now()->toISOString(),
        ];

        // Guardar el error directamente en Redis
        if ($this->batchId) {
            Redis::rpush("batch:{$this->batchId}:errors", json_encode($error));
        } else {
            // Si no hay batchId, acumular en memoria para el retorno inmediato (ej. pruebas)
            $this->errors[] = $error;
        }

        // Log solo errores críticos para no saturar los logs
        if (in_array($type, ['header_count_mismatch', 'missing_header', 'business_logic_validation', 'system_error'])) {
            Log::warning('Excel validation error', $error);
        }
    }

    private function getColumnNumber(string $attribute): int
    {
        $headers = $this->config['expected_headers'];
        if (! $this->config['case_sensitive_headers']) {
            $headers = array_map('strtoupper', $headers);
            $attribute = strtoupper($attribute);
        }
        $position = array_search($attribute, $headers);

        return $position !== false ? $position + 1 : 0;
    }

    private function getColumnName(int $column): string
    {
        $headers = $this->config['expected_headers'];

        return $headers[$column - 1] ?? 'N/A';
    }

    public function getErrors(): array
    {
        // Este método ahora solo devolverá errores si no se usó Redis (ej. en pruebas directas)
        // o si se necesita una copia de los errores para el retorno inmediato.
        // La fuente principal de errores persistentes será la DB vía FinalizeImportDecisionJob.
        return $this->errors;
    }

    public function isValid(): bool
    {
        // Si se usa Redis, la validez se determina por la ausencia de errores en Redis
        // o por el estado del batch. Para este validador, si addError se llamó, no es válido.
        return empty($this->errors) && ($this->batchId ? Redis::llen("batch:{$this->batchId}:errors") == 0 : true);
    }

    public function getStats(): array
    {
        $endTime = microtime(true);
        $executionTime = round($endTime - $this->startTime, 2);

        // Contar errores de Redis si el batchId está disponible
        $errorCount = $this->batchId ? Redis::llen("batch:{$this->batchId}:errors") : count($this->errors);

        return [
            'rows_processed' => $this->rowsProcessed,
            'valid_rows' => $this->validRowsCount, // Esto no se actualiza en este validador, solo en ProcessDataChunkJob
            'error_count' => $errorCount,
            'validation_mode' => $this->config['validate_data'] ? 'full' : 'headers_only',
            'max_rows_limit' => $this->config['max_rows'],
            'sample_size' => $this->config['sample_size'],
            'execution_time_seconds' => $executionTime,
            'rows_per_second' => $this->rowsProcessed > 0 ? round($this->rowsProcessed / $executionTime, 2) : 0,
            'config_used' => [
                'validate_headers' => $this->config['validate_headers'],
                'validate_data' => $this->config['validate_data'],
                'strict_header_order' => $this->config['strict_header_order'],
                'case_sensitive_headers' => $this->config['case_sensitive_headers'],
                'skip_empty_rows' => $this->config['skip_empty_rows'],
                'stop_on_first_error' => $this->config['stop_on_first_error'],
            ],
        ];
    }

    public static function validateFile(
        string $filePath,
        array $expectedHeaders,
        array $validationRules = [],
        array $options = []
    ): array {
        if (! file_exists($filePath)) {
            return [
                'valid' => false,
                'errors' => [
                    [
                        'row_number' => 0,
                        'column_name' => 'FILE',
                        'error_message' => "Archivo no encontrado: {$filePath}",
                        'error_type' => 'file_not_found',
                        'error_value' => null,
                        'original_data' => null,
                        'timestamp' => now()->toISOString(),
                    ],
                ],
                'stats' => ['error_count' => 1],
            ];
        }

        $validator = new self(array_merge([
            'expected_headers' => $expectedHeaders,
        ], self::createValidationRules($validationRules), $options));

        try {
            Excel::import($validator, $filePath);

            // Si hay un batchId, los errores ya están en Redis.
            // Si no, se acumularon en $validator->errors.
            $errors = $validator->getErrors();
            if ($validator->batchId) {
                // Si se usó Redis, recupera los errores de Redis para el retorno final
                $redisErrors = Redis::lrange("batch:{$validator->batchId}:errors", 0, -1);
                $errors = array_map(fn ($e) => json_decode($e, true), $redisErrors);
            }

            return [
                'valid' => $validator->isValid(),
                'errors' => $errors,
                'stats' => $validator->getStats(),
            ];
        } catch (\Exception $e) {
            // Si ocurre una excepción durante la importación (ej. archivo corrupto)
            $systemError = [
                'row_number' => 0,
                'column_name' => 'SYSTEM',
                'error_message' => 'Error del sistema durante importación: '.$e->getMessage(),
                'error_type' => 'system_error',
                'error_value' => null,
                'original_data' => null,
                'timestamp' => now()->toISOString(),
            ];

            // Si hay batchId, guardar este error de sistema en Redis también
            if ($validator->batchId) {
                Redis::rpush("batch:{$validator->batchId}:errors", json_encode($systemError));
            } else {
                // Si no hay batchId, añadirlo a la lista de errores en memoria
                $validator->errors[] = $systemError;
            }

            // Recuperar todos los errores (incluyendo el nuevo error de sistema)
            $errors = $validator->getErrors();
            if ($validator->batchId) {
                $redisErrors = Redis::lrange("batch:{$validator->batchId}:errors", 0, -1);
                $errors = array_map(fn ($e) => json_decode($e, true), $redisErrors);
            }

            return [
                'valid' => false,
                'errors' => $errors,
                'stats' => $validator->getStats(),
                'exception' => $e->getMessage(),
            ];
        }
    }

    public static function createValidationRules(array $fieldValidations): array
    {
        $rules = [];
        foreach ($fieldValidations as $field => $validations) {
            $fieldRules = [];
            foreach ($validations as $type => $params) {
                switch ($type) {
                    case 'required':
                        if ($params) {
                            $fieldRules[] = 'required';
                        }
                        break;
                    case 'numeric':
                        $fieldRules[] = 'numeric';
                        if (isset($params['min'])) {
                            $fieldRules[] = "min:{$params['min']}";
                        }
                        if (isset($params['max'])) {
                            $fieldRules[] = "max:{$params['max']}";
                        }
                        break;
                    case 'string':
                        $fieldRules[] = 'string';
                        if (isset($params['max_length'])) {
                            $fieldRules[] = "max:{$params['max_length']}";
                        }
                        if (isset($params['min_length'])) {
                            $fieldRules[] = "min:{$params['min_length']}";
                        }
                        break;
                    case 'date':
                        $fieldRules[] = 'date';
                        if (isset($params['format'])) {
                            $fieldRules[] = "date_format:{$params['format']}";
                        }
                        break;
                    case 'in':
                        $values = is_array($params) ? $params : [$params];
                        $fieldRules[] = Rule::in($values);
                        break;
                    case 'regex':
                        $fieldRules[] = "regex:{$params}";
                        break;
                    case 'email':
                        $fieldRules[] = 'email';
                        break;
                    case 'boolean':
                        $fieldRules[] = 'boolean';
                        break;
                    case 'custom':
                        if (is_array($params)) {
                            $fieldRules = array_merge($fieldRules, $params);
                        } else {
                            $fieldRules[] = $params;
                        }
                        break;
                }
            }
            if (! empty($fieldRules)) {
                $rules[$field] = $fieldRules;
            }
        }

        return ['custom_validations' => $rules];
    }
}
