<?php

namespace App\Services\Conciliation;

use App\Models\AuditoryFinalReport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ConciliationValidator
{
    protected $batchId ;
    protected $validationRules = [
        'data_integrity' => true,
        'required_fields' => true,
        'estado_respuesta' => true,
        'valor_aceptado_ips' => true,
        'valor_aceptado_eps' => true,
        'valor_ratificado_eps' => true,
        'cross_amount_validation' => true,
        'complete_invoices' => true,
    ];

    // Campos obligatorios explícitos
    protected $requiredFields = [
        'ID',
        'FACTURA_ID',
        'ESTADO_RESPUESTA',
        'NUMERO_DE_AUTORIZACION',
        'VALOR_ACEPTADO_IPS',
        'VALOR_ACEPTADO_EPS',
        'VALOR_RATIFICADO_EPS',
        'OBSERVACIONES'
    ];

    // Clave para almacenar el conteo de facturas en cache
    protected const FACTURA_COUNT_CACHE_KEY = 'factura_counts';

    // Clave para almacenar el conteo de apariciones en el Excel
    protected const FACTURA_EXCEL_COUNT_CACHE_KEY = 'factura_excel_counts';

    // Tiempo de vida de la caché en minutos (2 horas = 120 minutos)
    protected const CACHE_TTL_MINUTES = 120;

    public function validate(array $row, array $rowData, int $rowIndex, array $expectedHeaders,$batchId): array
    {
        $this->batchId = $batchId;
        $errors = [];
        $reportData = null;

        // 1. Validación de facturas completas (solo si tiene FACTURA_ID)
        if (isset($row['FACTURA_ID']) && !empty($row['FACTURA_ID'])) {
            $errors = array_merge($errors, $this->validateCompleteInvoices($row, $rowIndex, $rowData));
        }

        // Validación de integridad de datos
        if ($this->validationRules['data_integrity'] || $this->validationRules['cross_amount_validation']) {
            if (isset($row['ID']) && !empty($row['ID'])) {
                $cacheKey = "auditory_report:{$row['ID']}";

                $reportData = Cache::remember($cacheKey, self::CACHE_TTL_MINUTES, function () use ($row) {
                    try {
                        $report = AuditoryFinalReport::where('id', $row['ID'])->first();
                        return $report ? ['valor_glosa' => (float) $report->valor_glosa] : null;
                    } catch (\Exception $e) {
                        Log::error("Error retrieving auditory report: " . $e->getMessage());
                        return null;
                    }
                });

                if ($reportData === null) {
                    $errors[] = $this->buildError(
                        'datos',
                        $rowIndex,
                        'ID',
                        "El ID {$row['ID']} no existe en auditory_final_reports o hubo un error al consultar.",
                        $rowData,
                        $row['ID'] ?? null
                    );
                }
            }
        }

        // Validar campos obligatorios
        if ($this->validationRules['required_fields']) {
            foreach ($this->requiredFields as $field) {
                if (!$this->isValidRequired($row, $field)) {
                    $errors[] = $this->buildError(
                        'requerido',
                        $rowIndex,
                        $field,
                        "El campo '{$field}' es obligatorio y está vacío o es inválido.",
                        $rowData,
                        $row[$field] ?? null
                    );
                }
            }
        }

        // Validación específica de ESTADO_RESPUESTA
        if ($this->validationRules['estado_respuesta'] && isset($row['ESTADO_RESPUESTA']) && !empty($row['ESTADO_RESPUESTA'])) {
            $allowedStatuses = [
                'Glosa aceptada por IPS',
                'Glosa No aceptada por IPS (Genera respuesta)',
                'Glosa Subsanada por IPS (Genera respuesta y Envía soporte)',
                'Glosa para conciliación'
            ];

            if (!in_array($row['ESTADO_RESPUESTA'], $allowedStatuses, true)) {
                $errors[] = $this->buildError(
                    'formato',
                    $rowIndex,
                    'ESTADO_RESPUESTA',
                    "Valor no permitido en ESTADO_RESPUESTA. Valores válidos: " . implode(', ', $allowedStatuses),
                    $rowData,
                    $row['ESTADO_RESPUESTA']
                );
            }
        }

        // Validación de valores numéricos positivos
        $numericFields = [
            'VALOR_ACEPTADO_IPS' => $this->validationRules['valor_aceptado_ips'],
            'VALOR_ACEPTADO_EPS' => $this->validationRules['valor_aceptado_eps'],
            'VALOR_RATIFICADO_EPS' => $this->validationRules['valor_ratificado_eps']
        ];

        foreach ($numericFields as $field => $shouldValidate) {
            if ($shouldValidate && isset($row[$field]) && (!is_numeric($row[$field]) || $row[$field] < 0)) {
                $errors[] = $this->buildError(
                    'formato',
                    $rowIndex,
                    $field,
                    "El campo '{$field}' debe ser un valor numérico positivo.",
                    $rowData,
                    $row[$field]
                );
            }
        }

        // Validación cruzada de montos
        if ($this->validationRules['cross_amount_validation'] && empty($errors)) {
            if (
                isset($row['VALOR_ACEPTADO_IPS'], $row['VALOR_ACEPTADO_EPS'], $row['VALOR_RATIFICADO_EPS']) &&
                is_numeric($row['VALOR_ACEPTADO_IPS']) &&
                is_numeric($row['VALOR_ACEPTADO_EPS']) &&
                is_numeric($row['VALOR_RATIFICADO_EPS']) &&
                $reportData && isset($reportData['valor_glosa'])
            ) {

                $sum = (float) $row['VALOR_ACEPTADO_IPS'] +
                    (float) $row['VALOR_ACEPTADO_EPS'] +
                    (float) $row['VALOR_RATIFICADO_EPS'];

                $valorGlosa = (float) $reportData['valor_glosa'];

                if ($sum !== $valorGlosa) {
                    $errors[] = $this->buildError(
                        'consistencia',
                        $rowIndex,
                        'VALOR_ACEPTADO_IPS,VALOR_ACEPTADO_EPS,VALOR_RATIFICADO_EPS',
                        "La suma de valores ({$sum}) no coincide con VALOR_GLOSA ({$valorGlosa}).",
                        $rowData,
                        $sum
                    );
                }
            }
        }

        return $errors;
    }

    protected function buildError(
        string $errorType,
        int $rowIndex,
        string $column,
        string $message,
        array $fullRecord,
        $field_value = null
    ): array {
        return [
            'error_type' => $errorType,
            'row' => $rowIndex,
            'column' => $column,
            'message' => $message,
            'full_record' => $fullRecord,
            'field_value' => $field_value
        ];
    }

    protected function isValidRequired(array $row, string $field): bool
    {
        // Verifica que el campo exista y no esté vacío (incluyendo '0' como válido)
        return isset($row[$field]) &&
            $row[$field] !== '' &&
            $row[$field] !== null &&
            (!is_string($row[$field]) || trim($row[$field]) !== '');
    }

    /**
     * Validación de facturas completas
     */
    protected function validateCompleteInvoices(array $row, int $rowIndex, array $rowData): array
    {
        $errors = [];
        $facturaId = $row['FACTURA_ID'];

        try {
            // 1. Registrar aparición en el Excel
            $this->incrementExcelCount($facturaId);

            // 2. Obtener conteo de la base de datos (con cache)
            $dbCount = $this->getDatabaseCount($facturaId);

            // 3. Validar solo si ya terminamos de procesar todo el archivo
            // (esta validación se completará en el método finalizeValidation)

        } catch (\Exception $e) {
            $errors[] = $this->buildError(
                'datos',
                $rowIndex,
                'FACTURA_ID',
                "Error al validar factura: " . $e->getMessage(),
                $rowData,
                $facturaId
            );
        }

        return $errors;
    }

    /**
     * Incrementa el contador de apariciones en el Excel
     */
    protected function incrementExcelCount(string $facturaId): void
    {
        $excelCounts = Cache::get(self::FACTURA_EXCEL_COUNT_CACHE_KEY. ":{$this->batchId}", []);
        $excelCounts[$facturaId] = ($excelCounts[$facturaId] ?? 0) + 1;
        Cache::put(self::FACTURA_EXCEL_COUNT_CACHE_KEY. ":{$this->batchId}", $excelCounts, self::CACHE_TTL_MINUTES);
    }

    /**
     * Obtiene el conteo de registros en la base de datos para una factura (con cache)
     */
    protected function getDatabaseCount(string $facturaId): int
    {
        return Cache::remember(
            self::FACTURA_COUNT_CACHE_KEY . ":{$facturaId}". ":{$this->batchId}",
            self::CACHE_TTL_MINUTES,
            function () use ($facturaId) {
                return AuditoryFinalReport::where('factura_id', $facturaId)
                    ->where('valor_glosa', '>', 0)
                    ->count();
            }
        );
    }

    /**
     * Método para finalizar la validación (debe llamarse al terminar de procesar todo el archivo)
     */
    public function finalizeValidation(): array
    {
        $errors = [];
        $excelCounts = Cache::get(self::FACTURA_EXCEL_COUNT_CACHE_KEY. ":{$this->batchId}", []);

        foreach ($excelCounts as $facturaId => $excelCount) {
            $dbCount = $this->getDatabaseCount($facturaId);

            if ($excelCount !== $dbCount) {
                $errors[] = [
                    'error_type' => 'factura_incompleta',
                    'row' => 'global',
                    'column' => 'FACTURA_ID',
                    'message' => "La factura {$facturaId} está incompleta. Registros en Excel: {$excelCount}, Registros en BD: {$dbCount}",
                    'full_record' => null,
                    'field_value' => $facturaId
                ];
            }
        }

        // Limpiar cache de conteos
        $this->clearCountCache();

        return $errors;
    }

    /**
     * Limpia los datos de cache de conteo
     */
    protected function clearCountCache(): void
    {
        $excelCounts = Cache::get(self::FACTURA_EXCEL_COUNT_CACHE_KEY. ":{$this->batchId}", []);

        // Eliminar conteos individuales de la BD
        foreach (array_keys($excelCounts) as $facturaId) {
            Cache::forget(self::FACTURA_COUNT_CACHE_KEY . ":{$facturaId}". ":{$this->batchId}");
        }

        // Eliminar conteo del Excel
        Cache::forget(self::FACTURA_EXCEL_COUNT_CACHE_KEY. ":{$this->batchId}");
    }
}
