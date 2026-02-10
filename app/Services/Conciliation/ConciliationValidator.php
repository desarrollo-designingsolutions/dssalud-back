<?php

namespace App\Services\Conciliation;

use App\Models\AuditoryFinalReport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; // Importar Redis
use Illuminate\Support\Facades\Redis;    // Importar DB

class ConciliationValidator
{
    protected $batchId;

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
        'SERVICIO_ID',
        'ESTADO_RESPUESTA',
        'VALOR_ACEPTADO_IPS',
        'VALOR_ACEPTADO_EPS',
        'VALOR_RATIFICADO_EPS',
        'OBSERVACIONES',
    ];

    // Clave para almacenar el conteo de facturas en cache (para DB counts)
    protected const FACTURA_COUNT_CACHE_KEY = 'factura_counts';

    // Clave para almacenar el conteo de apariciones en el Excel (usando Redis Hash)
    protected const FACTURA_EXCEL_COUNT_CACHE_KEY = 'factura_excel_counts';

    // Tiempo de vida de la caché en minutos (2 horas = 120 minutos)
    protected const CACHE_TTL_MINUTES = 120;

    /**
     * Constructor de la clase.
     *
     * @param  string|null  $batchId  El ID del batch actual.
     */
    public function __construct(?string $batchId = null)
    {
        $this->batchId = $batchId;
    }

    public function validate(array $row, array $rowData, int $rowIndex, array $expectedHeaders): array
    {
        // El batchId ya se establece en el constructor, no es necesario pasarlo aquí.
        $errors = [];
        $reportData = null;

        // 1. Validación de facturas completas (solo si tiene FACTURA_ID)
        if (isset($row['FACTURA_ID']) && ! empty($row['FACTURA_ID'])) {
            $errors = array_merge($errors, $this->validateCompleteInvoices($row, $rowIndex, $rowData));
        }

        // Validación de integridad de datos
        if ($this->validationRules['data_integrity'] || $this->validationRules['cross_amount_validation']) {
            if (isset($row['ID']) && ! empty($row['ID'])) {
                $cacheKey = "auditory_report:{$row['ID']}".":{$this->batchId}";
                $reportData = Cache::remember($cacheKey, self::CACHE_TTL_MINUTES, function () use ($row) {
                    try {
                        $report = AuditoryFinalReport::where('id', $row['ID'])->first();

                        return $report ? ['valor_glosa' => (float) $report->valor_glosa] : null;
                    } catch (\Exception $e) {
                        Log::error('Error retrieving auditory report: '.$e->getMessage());

                        return null;
                    }
                });
                if ($reportData === null) {
                    $errors[] = $this->buildError(
                        'datos',
                        $rowIndex,
                        'ID',
                        "El ID {$row['ID']} no existe en auditory_final_reports o hubo un error al consultar.",
                        $row['ID'] ?? null,
                        $rowData,
                    );
                }
            }
        }

        // Validar campos obligatorios
        if ($this->validationRules['required_fields']) {
            foreach ($this->requiredFields as $field) {
                if (! $this->isValidRequired($row, $field)) {
                    $errors[] = $this->buildError(
                        'requerido',
                        $rowIndex,
                        $field,
                        "El campo '{$field}' es obligatorio y está vacío o es inválido.",
                        $row[$field] ?? null,
                        $rowData,
                    );
                }
            }
        }

        // Validación específica de ESTADO_RESPUESTA
        if ($this->validationRules['estado_respuesta'] && isset($row['ESTADO_RESPUESTA']) && ! empty($row['ESTADO_RESPUESTA'])) {
            $allowedStatuses = [
                'Glosa aceptada por IPS',
                'Glosa No aceptada por IPS (Genera respuesta)',
                'Glosa Subsanada por IPS (Genera respuesta y Envía soporte)',
                'Glosa para conciliación',
            ];
            if (! in_array($row['ESTADO_RESPUESTA'], $allowedStatuses, true)) {
                $errors[] = $this->buildError(
                    'formato',
                    $rowIndex,
                    'ESTADO_RESPUESTA',
                    'Valor no permitido en ESTADO_RESPUESTA. Valores válidos: '.implode(', ', $allowedStatuses),
                    $row['ESTADO_RESPUESTA'],
                    $rowData,
                );
            }
        }

        // Validación de valores numéricos positivos
        $numericFields = [
            'VALOR_ACEPTADO_IPS' => $this->validationRules['valor_aceptado_ips'],
            'VALOR_ACEPTADO_EPS' => $this->validationRules['valor_aceptado_eps'],
            'VALOR_RATIFICADO_EPS' => $this->validationRules['valor_ratificado_eps'],
        ];
        foreach ($numericFields as $field => $shouldValidate) {
            if ($shouldValidate && isset($row[$field]) && (! is_numeric($row[$field]) || $row[$field] < 0)) {
                $errors[] = $this->buildError(
                    'formato',
                    $rowIndex,
                    $field,
                    "El campo '{$field}' debe ser un valor numérico positivo.",
                    $row[$field],
                    $rowData,
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
                        $sum,
                        $rowData,
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
        $error_value,
        array $fullRecord,
    ): array {
        return [
            'error_type' => $errorType,
            'row_number' => $rowIndex, // Cambiado a row_number para consistencia con la tabla de errores
            'column_name' => $column,
            'error_message' => $message,
            'original_data' => $fullRecord,
            'error_value' => $error_value,
            'timestamp' => now()->toISOString(), // Añadido timestamp
        ];
    }

    protected function isValidRequired(array $row, string $field): bool
    {
        // Verifica que el campo exista y no esté vacío (incluyendo '0' como válido)
        return isset($row[$field]) &&
            $row[$field] !== '' &&
            $row[$field] !== null &&
            (! is_string($row[$field]) || trim($row[$field]) !== '');
    }

    /**
     * Validación de facturas completas
     */
    protected function validateCompleteInvoices(array $row, int $rowIndex, array $rowData): array
    {
        $errors = [];
        $facturaId = $row['FACTURA_ID'];
        try {
            // 1. Registrar aparición en el Excel usando Redis para atomicidad
            $this->incrementExcelCount($facturaId);
            // 2. Obtener conteo de la base de datos (con cache)
            // Esta llamada es solo para que el cache se llene si es necesario,
            // la validación real de conteos se hace en finalizeValidation.
            $this->getDatabaseCount($facturaId);
        } catch (\Exception $e) {
            $errors[] = $this->buildError(
                'datos',
                $rowIndex,
                'FACTURA_ID',
                'Error al validar factura: '.$e->getMessage(),
                $facturaId,
                $rowData,
            );
        }

        return $errors;
    }

    /**
     * Incrementa el contador de apariciones en el Excel usando Redis Hash.
     * Esto es atómico y seguro para múltiples workers.
     */
    protected function incrementExcelCount(string $facturaId): void
    {
        if ($this->batchId) {
            Redis::hincrby(self::FACTURA_EXCEL_COUNT_CACHE_KEY.":{$this->batchId}", $facturaId, 1);
        } else {
            Log::warning('ConciliationValidator: batchId no está configurado. No se puede incrementar el conteo de Excel en Redis.');
        }
    }

    /**
     * Obtiene el conteo de registros en la base de datos para una factura (con cache).
     * Asume que AuditoryFinalReport es el modelo de tu tabla final.
     */
    protected function getDatabaseCount(string $facturaId): int
    {
        if (! $this->batchId) {
            Log::warning('ConciliationValidator: batchId no está configurado. No se puede obtener el conteo de BD desde caché.');

            return 0;
        }

        return Cache::remember(
            self::FACTURA_COUNT_CACHE_KEY.":{$facturaId}".":{$this->batchId}",
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
     * y verificar la conciliación de conteos de FACTURA_ID con valor_glosa > 0.
     *
     * @return array Una lista de errores encontrados.
     */
    public function finalizeValidation(): array
    {
        $errors = [];
        if (! $this->batchId) {
            Log::error('ConciliationValidator: batchId no está configurado en finalizeValidation. No se puede realizar la validación final.');

            return [['error_type' => 'system_error', 'message' => 'Batch ID no disponible para validación final.']];
        }

        // 1. Obtener todos los conteos de Excel desde Redis (hash)
        $excelCounts = Redis::hgetall(self::FACTURA_EXCEL_COUNT_CACHE_KEY.":{$this->batchId}");
        // Convertir los valores de string a int
        $excelCounts = array_map('intval', $excelCounts);

        if (empty($excelCounts)) {
            $this->clearCountCache(); // Limpiar incluso si no hay conteos

            return [];
        }

        $facturaIds = array_keys($excelCounts);
        // Log::info("facturaIds: " . implode(', ', $facturaIds));

        // 2. Obtener conteos de la base de datos desde Redis
        $dbCounts = [];
        foreach ($facturaIds as $facturaId) {
            $redisKey = "invoice_audit:{$facturaId}:db_count";
            $dbCount = Redis::connection('redis_6380')->get($redisKey);
            $dbCounts[$facturaId] = $dbCount !== null ? (int) $dbCount : 0;
        }

        // Log::info("Conteo de Redis terminado para facturaIds");

        // 3. Comparar conteos
        foreach ($excelCounts as $facturaId => $excelCount) {
            $dbCount = $dbCounts[$facturaId] ?? 0; // Obtener conteo de Redis, 0 si no se encuentra

            if ($excelCount !== $dbCount) {
                $errors[] = [
                    'error_type' => 'final_conciliation_error',
                    'row_number' => 0,
                    'column_name' => 'FACTURA_ID_CONCILIACION',
                    'error_message' => "La factura {$facturaId} está incompleta. Registros en Excel: {$excelCount}, Registros en Redis (db_count): {$dbCount}",
                    'error_value' => $facturaId,
                    'original_data' => ['factura_id' => $facturaId, 'excel_count' => $excelCount, 'db_count' => $dbCount],
                    'timestamp' => now()->toISOString(),
                ];
                // Log::warning("Error de conciliación final para FACTURA_ID {$facturaId}: Conteo Excel ({$excelCount}) vs Redis (db_count: {$dbCount}).");
            }
        }

        // Limpiar cache de conteos
        $this->clearCountCache();

        return $errors;
    }

    /**
     * Limpia los datos de cache de conteo.
     * Elimina el hash de Redis para los conteos de Excel.
     * Para los conteos de BD, se confía en el TTL de Cache::remember.
     */
    protected function clearCountCache(): void
    {
        if ($this->batchId) {
            Redis::del(self::FACTURA_EXCEL_COUNT_CACHE_KEY.":{$this->batchId}");
            // Los cachés individuales de DB (FACTURA_COUNT_CACHE_KEY) se gestionan por su propio TTL
            // o se podrían borrar explícitamente si se pasara la lista de facturaIds aquí.
            // Por simplicidad y eficiencia, confiamos en el TTL para los cachés de DB.
        } else {
            Log::warning('ConciliationValidator: batchId no está configurado. No se puede limpiar la caché de conteos.');
        }
    }
}
