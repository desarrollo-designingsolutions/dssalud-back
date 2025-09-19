<?php

namespace App\Imports\ConciliationImport\Services;

use App\Imports\ConciliationImport\Traits\ImportHelper;
use App\Models\AuditoryFinalReport;
use App\Models\InvoiceAudit;
use App\Models\ReconciliationGroupInvoice;
use App\Services\CacheService;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;

class CsvValidationService
{
    use ImportHelper;
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

    public function validateCsv(string $filePath, string $reconciliation_group_id): array
    {
        $cachePrefix = config('database.redis.options.prefix', '');
        Log::info("ingreso en validateCsv");

        // Redis::connection('redis_6380')->del("import_errors:{$this->batchId}");

        $keysToClean = Redis::connection('redis_6380')->keys($cachePrefix . "csv_factura_total_counts:{$this->batchId}");
        $keysToClean = array_merge($keysToClean, Redis::connection('redis_6380')->keys($cachePrefix . "csv_factura_rows:{$this->batchId}:*"));
        $keysToClean = array_merge($keysToClean, Redis::connection('redis_6380')->keys($cachePrefix . "csv_unique_factura_ids:{$this->batchId}"));

        if (! empty($keysToClean)) {
            // Log::info(sprintf("DEBUG VALIDATION: Limpiando %d claves de Redis al inicio de la validación.", count($keysToClean)));
            Redis::connection('redis_6380')->del($keysToClean);
        } else {
            // Log::info("DEBUG VALIDATION: No se encontraron claves de Redis para limpiar al inicio de la validación.");
        }

        // Paso 1: Validación de cabeceras y filas (aquí es donde el 'progress' principal aumentará)
        if ($this->eventDispatcher) {
            ($this->eventDispatcher)(0, 'Validando cabeceras', 'active', 'Validando cabeceras...');
        }
        $this->validateHeaders($filePath);
        // Log::info("Header validation passed for batch ID: {$this->batchId}. Proceeding with row validation.");

        if (Redis::connection('redis_6380')->llen("import_errors:{$this->batchId}") > 0) {
            // Log::warning("Header validation failed for batch ID: {$this->batchId}. Stopping further validation.");
            if ($this->eventDispatcher) {
                ($this->eventDispatcher)(0, 'Error en cabeceras CSV', 'failed', '0');
            }

            return $this->getErrors();
        }

        // : Extracción y precarga de glosa
        if ($this->eventDispatcher) {
            ($this->eventDispatcher)(0, 'Extrayendo IDs para precarga de información', 'active', 'Extrayendo IDs para precarga de información...');
        }
        $valuesFromCsv = $this->getUniqueValuesFromCsv($filePath, ['ID', "FACTURA_ID"]);

        if (! empty($valuesFromCsv)) {
            $fields = [
                'ID' => ['model' => AuditoryFinalReport::class, 'field' => 'valor_glosa'],
                'FACTURA_ID' => ['model' => InvoiceAudit::class, 'field' => 'id'],
            ];

            $this->preloadAuditoryFieldsForCsvIds($filePath, $valuesFromCsv, $fields);
            Log::info("Precarga de datos completada.");
            if ($this->eventDispatcher) {
                ($this->eventDispatcher)(0, 'Precarga de datos completada', 'active', 'Valores precargados');
            }


            if ($this->eventDispatcher) {
                ($this->eventDispatcher)(
                    0,
                    'Validación de facturas contra grupo de conciliación',
                    'active',
                    'Verificando IDs en reconciliation_group_invoices...'
                );
            }

            Log::info("valuesFromCsv", $valuesFromCsv["FACTURA_ID"]);
            //comentado para el sabado pruebas con cliente
            // $this->validarFacturaIdsEnReconciliationGroupInvoice($valuesFromCsv["FACTURA_ID"], $reconciliation_group_id);
            // if ($this->eventDispatcher) {
            //     ($this->eventDispatcher)(
            //         0,
            //         'Validación contra grupo completada',
            //         'active',
            //         'Facturas validadas con reconciliation_group_invoices'
            //     );
            // }
        } else {
            // Log::warning("No se encontraron valores en el CSV para precargar datos de auditoría para la validación.");
        }



        if ($this->eventDispatcher) {
            ($this->eventDispatcher)(0, 'Validación de lineas', 'active', 'Validación de lineas...');
        }
        $this->validateRows($filePath);

        // Validar duplicados de auditory_final_report_id de forma concurrente
        if ($this->eventDispatcher) {
            ($this->eventDispatcher)(0, 'Validar duplicados', 'active', 'Validar duplicados...');
        }
        $this->validateAuditoryFinalReportIds($filePath);

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
        if ($headers && ! empty($headers[0])) {
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        }
        fclose($handle); // Cerrar el archivo después de leer los encabezados

        // Log::info('Headers read from CSV:', [$headers]);
        // Log::info('Expected headers (exact match):', [$this->requiredHeaders]);

        // Comparar las cabeceras leídas directamente con las cabeceras requeridas (sensible a la capitalización)
        $missingHeaders = array_diff($this->requiredHeaders, $headers);

        if (! empty($missingHeaders)) {
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
                sprintf('Number of headers mismatch. Expected %d, found %d.', count($this->requiredHeaders), count($headers)),
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
            if ($actualHeaders && ! empty($actualHeaders[0])) {
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
            //recorre el arreglo recursivamente y convierte todas las cadenas a UTF-8 usando utf8_encode
            $data = ensureUtf8($data);

            // Recolección de datos para la validación de "Facturas Completas"
            $facturaId = trim($data['FACTURA_ID'] ?? '');
            $auditoryReportId = trim($data['ID'] ?? '');

            if (! empty($facturaId) && ! empty($auditoryReportId)) {
                Redis::connection('redis_6380')->hincrby("csv_factura_total_counts:{$this->batchId}", $facturaId, 1);
                Redis::connection('redis_6380')->rpush("csv_factura_rows:{$this->batchId}:{$facturaId}", $rowNumber);
                Redis::connection('redis_6380')->sadd("csv_unique_factura_ids:{$this->batchId}", $facturaId);
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
                if (! isset($data[$field]) || trim($data[$field]) === '') {
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
            if (! in_array(trim($data['ESTADO_RESPUESTA'] ?? ''), $validStatuses)) {
                $this->addError($rowNumber, 'ESTADO_RESPUESTA', 'Invalid response status (exact match required)', 'invalid_value', $data['ESTADO_RESPUESTA'] ?? '', json_encode($data));
            }

            // 3. Validación de campos numéricos y positivos
            $numericPositiveFields = [
                'VALOR_ACEPTADO_POR_IPS',
                'VALOR_ACEPTADO_POR_EPS',
                'VALOR_RATIFICADO_EPS',
            ];

            foreach ($numericPositiveFields as $field) {
                if (! isset($data[$field]) || trim($data[$field]) === '') {
                    continue;
                }

                $value = str_replace(',', '.', trim($data[$field]));

                if (! is_numeric($value)) {
                    $this->addError($rowNumber, $field, "El campo '$field' debe ser un valor numérico", 'invalid_numeric', $data[$field], json_encode($data));
                } elseif ((float) $value < 0) {
                    $this->addError($rowNumber, $field, "El campo '$field' debe ser un valor numérico positivo", 'negative_value', $data[$field], json_encode($data));
                }
            }

            // 4. Validación cruzada de montos (usando Redis con clave específica del batch)
            if (isset($data['ID']) && ! empty(trim($data['ID']))) {
                $auditoryReportId = trim($data['ID']);
                $redisKey = "auditoryfinalreport_fields_master";

                // Obtener el valor JSON desde el hash
                $jsonValue = Redis::connection('redis_6380')->hget($redisKey, $auditoryReportId);
                // Obtener el valor de valor_glosa
                $expectedValorGlosa = json_decode($jsonValue, true)['valor_glosa'] ?? null;
                // log::info("auditoryReportId",[$auditoryReportId]);
                // log::info("jsonValue",[$jsonValue]);

                // log::info("expectedValorGlosa",[$expectedValorGlosa]);

                if (is_null($expectedValorGlosa)) {
                    $this->addError($rowNumber, 'ID', "ID '$auditoryReportId' no encontrado en auditory_final_reports o no precargado.", 'id_not_found', $data['ID'], json_encode($data));
                } else {
                    // comentado para el sabado pruebas con cliente

                    // $valorAceptadoIps = (float) str_replace(',', '.', trim($data['VALOR_ACEPTADO_POR_IPS'] ?? '0'));
                    // $valorAceptadoEps = (float) str_replace(',', '.', trim($data['VALOR_ACEPTADO_POR_EPS'] ?? '0'));
                    // $valorRatificadoEps = (float) str_replace(',', '.', trim($data['VALOR_RATIFICADO_EPS'] ?? '0'));

                    // $sumAcceptedValues = $valorAceptadoIps + $valorAceptadoEps + $valorRatificadoEps;

                    // $expectedValorGlosaFloat = (float) $expectedValorGlosa;

                    // if (abs($sumAcceptedValues - $expectedValorGlosaFloat) > 0.01) {
                    //     $this->addError($rowNumber, 'amounts', sprintf(
                    //         "La suma de valores aceptados (%.2f) no coincide con valor_glosa (%.2f) para ID '%s'",
                    //         $sumAcceptedValues,
                    //         $expectedValorGlosaFloat,
                    //         $auditoryReportId
                    //     ), 'amount_mismatch', strval($sumAcceptedValues), json_encode($data));
                    // }
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
                (string) $rowNumber // Detalle en currentStudent
            );
        }
    }


    /**
     * Valida concurrentemente si los auditory_final_report_id ya existen en conciliation_results.
     *
     * @param string $filePath Ruta completa al archivo CSV.
     */
    protected function validateAuditoryFinalReportIds(string $filePath): void
    {
        Log::info("ingreso en validateAuditoryFinalReportIds");

        $numberOfProcesses = 10;
        $tasks = [];
        $batchSize = 100; // Tamaño del lote para cada tarea concurrente
        $requiredHeaders = $this->requiredHeaders; // Capturar encabezados para evitar usar $this en el closure



        $batchId = $this->batchId;
        for ($i = 0; $i < $numberOfProcesses; $i++) {
            $tasks[] = function () use ($filePath, $i, $numberOfProcesses, $batchSize, $requiredHeaders, $batchId) {
                DB::reconnect();
                $handle = fopen($filePath, 'r');
                fgetcsv($handle, 0, ';'); // Saltar cabecera
                $currentLine = 0;
                $idsToCheck = [];
                $rowsData = [];

                while (($row = fgetcsv($handle, 0, ';')) !== false) {
                    if ($currentLine++ % $numberOfProcesses !== $i) {
                        continue;
                    }
                    $row = array_map('trim', $row);
                    $row = ensureUtf8($row);
                    $auditory_final_report_id = trim($row[0] ?? '');
                    if (!empty($auditory_final_report_id)) {
                        $idsToCheck[] = [
                            'id' => $auditory_final_report_id,
                            'rowNumber' => $currentLine + 1, // +1 porque se salta la cabecera
                        ];
                        $rowsData[] = $row;
                    }

                    if (count($idsToCheck) == $batchSize) {
                        // Procesar lote de IDs
                        $ids = array_column($idsToCheck, 'id');
                        $existingIds = DB::table('conciliation_results')
                            ->whereIn('auditory_final_report_id', $ids)
                            ->pluck('auditory_final_report_id')
                            ->toArray();

                        foreach ($idsToCheck as $index => $item) {
                            if (in_array($item['id'], $existingIds)) {
                                // Mapear la fila a los encabezados requeridos para original_data
                                $data = array_combine($requiredHeaders, array_pad($rowsData[$index], count($requiredHeaders), ''));
                                $data = ensureUtf8($data);

                                $error = [
                                    'id' => (string) Str::uuid(),
                                    'batch_id' => $batchId,
                                    'row_number' => $item['rowNumber'],
                                    'column_name' => 'ID',
                                    'error_message' => "auditory_final_report_id '{$item['id']}' ya existe en conciliation_results",
                                    'error_type' => 'duplicate_auditory_final_report_id',
                                    'error_value' => $item['id'],
                                    'original_data' => json_encode($data),
                                    'created_at' => now()->toDateTimeString(),
                                    'updated_at' => now()->toDateTimeString(),
                                ];
                                Redis::connection('redis_6380')->rpush("import_errors:{$batchId}", json_encode($error));
                                Redis::connection('redis_6380')->expire("import_errors:{$batchId}", 3600);
                            }
                        }

                        $idsToCheck = [];
                        $rowsData = [];
                    }
                }

                // Procesar el lote final
                if (!empty($idsToCheck)) {
                    $ids = array_column($idsToCheck, 'id');
                    $existingIds = DB::table('conciliation_results')
                        ->whereIn('auditory_final_report_id', $ids)
                        ->pluck('auditory_final_report_id')
                        ->toArray();

                    foreach ($idsToCheck as $index => $item) {
                        if (in_array($item['id'], $existingIds)) {
                            $data = array_combine($requiredHeaders, array_pad($rowsData[$index], count($requiredHeaders), ''));
                            $data = ensureUtf8($data);

                            $error = [
                                'id' => (string) Str::uuid(),
                                'batch_id' => $batchId,
                                'row_number' => $item['rowNumber'],
                                'column_name' => 'ID',
                                'error_message' => "auditory_final_report_id '{$item['id']}' ya existe en conciliation_results",
                                'error_type' => 'duplicate_auditory_final_report_id',
                                'error_value' => $item['id'],
                                'original_data' => json_encode($data),
                                'created_at' => now()->toDateTimeString(),
                                'updated_at' => now()->toDateTimeString(),
                            ];
                            Redis::connection('redis_6380')->rpush("import_errors:{$batchId}", json_encode($error));
                            Redis::connection('redis_6380')->expire("import_errors:{$batchId}", 3600);
                        }
                    }
                }

                fclose($handle);
                return true;
            };
        }

        Concurrency::run($tasks);
    }



    /**
     * Valida y precarga múltiples campos de diferentes tablas a Redis.
     * Valida primero si los IDs existen en la BD antes de precargar.
     *
     * @param string $filePath Ruta al archivo CSV
     * @param array $uniqueValues Array asociativo de valores únicos por columna
     * @param array $fields Configuración de campos
     * @param bool $stopOnInvalid Si true, detiene la precarga si hay IDs no válidos
     * @return bool True si la precarga fue exitosa, false si se detuvo por IDs no válidos
     * @throws \Exception Si hay errores críticos
     */
    public function preloadAuditoryFieldsForCsvIds(string $filePath, array $uniqueValues, array $fields, bool $stopOnInvalid = true): bool
    {
        if (empty($fields) || empty($uniqueValues)) {
            Log::error('Error: No se proporcionaron campos o valores únicos para precargar.', ['fields' => $fields, 'uniqueValues' => $uniqueValues]);
            return false;
        }

        $cacheService = app(CacheService::class);
        $chunkSize = 1000;
        $preloadStartTime = microtime(true);
        $count = 0;
        $finalFoundIdsForBatch = [];

        $cacheService->clearByPrefix("auditory_fields:{$this->batchId}:");

        // Validar campos y valores únicos
        $missingIds = [];
        foreach ($fields as $fieldName => $config) {
            if (!isset($config['model']) || !class_exists($config['model']) || !isset($config['field'])) {
                Log::error("Error: Configuración inválida para el campo '$fieldName'. Debe incluir 'model' y 'field'.", ['config' => $config]);
                return false;
            }
            if (!isset($uniqueValues[$fieldName]) || empty($uniqueValues[$fieldName])) {
                Log::warning("Advertencia: No hay valores únicos para el campo '$fieldName' en el CSV.", ['field' => $fieldName]);
                $uniqueValues[$fieldName] = [];
            }

            // Validar IDs en la BD
            $fieldIds = array_map('strval', $uniqueValues[$fieldName]);
            if (!empty($fieldIds)) {
                $existingIds = [];
                foreach (array_chunk($fieldIds, $chunkSize) as $chunk) {
                    if (empty($chunk)) {
                        continue;
                    }
                    try {
                        $foundIds = $config['model']::whereIn('id', $chunk)->pluck('id')->toArray();
                        $existingIds = array_merge($existingIds, $foundIds);
                    } catch (\Exception $e) {
                        Log::error("Error al validar IDs para '$fieldName': " . $e->getMessage(), ['chunk' => array_slice($chunk, 0, 10)]);
                        throw $e;
                    }
                }
                $fieldMissingIds = array_diff($fieldIds, $existingIds);
                if (!empty($fieldMissingIds)) {
                    $missingIds[$fieldName] = $fieldMissingIds;
                }
            }
        }

        // Registrar IDs no válidos usando addError
        if (!empty($missingIds)) {
            $this->storeInvalidIdsWithRows($filePath, $missingIds);
            // if ($stopOnInvalid) {
            //     Log::error("Se encontraron IDs no válidos. Deteniendo precarga.", ['missing_ids' => array_map(fn($ids) => array_slice($ids, 0, 10), $missingIds)]);
            //     // return false;
            // }
        }

        // Paso 1: Identificar qué IDs están en la caché maestra de cada modelo y cuáles faltan
        $idsToLoadFromDb = [];
        $foundIdsInMasterCache = [];
        foreach ($fields as $fieldName => $config) {
            $modelClass = $config['model'];
            $redisMasterKey = $this->getRedisMasterKey($modelClass);
            $idsToLoadFromDb[$fieldName] = [];
            $foundIdsInMasterCache[$fieldName] = [];

            $fieldIds = array_map('strval', $uniqueValues[$fieldName]);
            if (empty($fieldIds)) {
                continue;
            }

            if (Redis::connection('redis_6380')->exists($redisMasterKey)) {
                foreach (array_chunk($fieldIds, $chunkSize) as $idChunk) {
                    if (empty($idChunk)) {
                        continue;
                    }
                    $idChunk = array_map('strval', $idChunk);
                    $fieldValues = Redis::connection('redis_6380')->hmget($redisMasterKey, $idChunk);
                    foreach ($idChunk as $index => $id) {
                        if (!is_null($fieldValues[$index])) {
                            $foundIdsInMasterCache[$fieldName][] = $id;
                        } else {
                            $idsToLoadFromDb[$fieldName][] = $id;
                        }
                    }
                }
            } else {
                $idsToLoadFromDb[$fieldName] = $fieldIds;
            }
        }

        // Paso 2: Cargar los IDs faltantes de la BD a la caché maestra de cada modelo
        foreach ($fields as $fieldName => $config) {
            $modelClass = $config['model'];
            $dbField = $config['field'];
            $redisMasterKey = $this->getRedisMasterKey($modelClass);

            if (!empty($idsToLoadFromDb[$fieldName])) {
                $dbLoadCount = 0;
                $dbLoadStartTime = microtime(true);
                $dbPipeline = Redis::connection('redis_6380')->pipeline();
                $processedChunks = 0;

                foreach (array_chunk($idsToLoadFromDb[$fieldName], $chunkSize) as $idChunkForDb) {
                    if (empty($idChunkForDb)) {
                        continue;
                    }
                    $processedChunks++;
                    $idChunkForDb = array_map('strval', $idChunkForDb);
                    $dbReports = $modelClass::select('id', $dbField)
                        ->whereIn('id', $idChunkForDb)
                        ->get();


                    foreach ($dbReports as $report) {
                        $jsonValue = json_encode($report);
                        $dbPipeline->hset($redisMasterKey, (string) $report->id, $jsonValue);
                        // Log: Registrar los datos guardados en la caché maestra
                        // Log::debug("Guardando en caché maestra", [
                        //     'redis_key' => $redisMasterKey,
                        //     'field' => (string) $report->id,
                        //     'value' => $jsonValue,
                        // ]);
                        $dbLoadCount++;
                    }
                }
                $dbPipeline->execute();
                Redis::connection('redis_6380')->expire($redisMasterKey, 60 * 60 * 24 * 180);
                $dbLoadEndTime = microtime(true);
            }
        }

        $preloadEndTime = microtime(true);
        // $notFoundIds = array_diff($primaryIds, $finalFoundIdsForBatch);
        // if (!empty($notFoundIds)) {
        //     Log::warning(sprintf("ATENCIÓN PRELOAD FIELDS: %d IDs no se encontraron en ninguna caché maestra.", count($notFoundIds)));
        // }

        return true;
    }

    /**
     * Registra IDs no válidos en Redis usando addError, mapeando a las filas del CSV.
     *
     * @param string $filePath Ruta al archivo CSV
     * @param array $missingIds Array asociativo de IDs no válidos por campo
     */
    protected function storeInvalidIdsWithRows(string $filePath, array $missingIds): void
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            Log::error("Error al abrir el CSV para mapear IDs no válidos: $filePath");
            return;
        }

        // Leer y normalizar encabezados
        $headers = fgetcsv($handle, 0, ';');
        if ($headers === false || empty($headers)) {
            Log::error("CSV vacío o sin encabezados: $filePath");
            fclose($handle);
            return;
        }

        // Normalizar encabezados (trim y uppercase)
        $normalizedHeaders = array_map(function ($header) {
            return strtoupper(trim(preg_replace('/^\xEF\xBB\xBF/', '', $header)));
        }, $headers);

        Log::info("Encabezados normalizados del CSV", ['headers' => $normalizedHeaders]);
        Log::info("Campos con IDs inválidos", ['fields' => array_keys($missingIds)]);

        $columnIndices = [];
        foreach (array_keys($missingIds) as $fieldName) {
            // Buscar el campo en los encabezados normalizados
            $normalizedField = strtoupper(trim($fieldName));
            $index = array_search($normalizedField, $normalizedHeaders);

            if ($index === false) {
                Log::error("Columna '$fieldName' no encontrada en el CSV. Encabezados disponibles: " . implode(', ', $normalizedHeaders));
                continue;
            }
            $columnIndices[$fieldName] = $index;
        }

        if (empty($columnIndices)) {
            Log::error("No se pudo mapear ningún campo inválido a columnas del CSV");
            fclose($handle);
            return;
        }

        $rowNumber = 0; // Encabezado es fila 0
        $pipeline = Redis::connection('redis_6380')->pipeline();

        LazyCollection::make(function () use ($handle) {
            while (($row = fgetcsv($handle, 0, ';')) !== false) {
                yield ensureUtf8($row);
            }
            fclose($handle);
        })->each(function ($row) use ($columnIndices, $missingIds, &$rowNumber, &$pipeline) {
            $rowNumber++;
            foreach ($columnIndices as $fieldName => $index) {
                if (isset($row[$index]) && !empty(trim($row[$index]))) {
                    $value = (string) trim($row[$index]);
                    if (in_array($value, $missingIds[$fieldName], true)) {
                        $this->addError(
                            rowNumber: $rowNumber,
                            columnName: $fieldName,
                            errorMessage: "ID '$value' no encontrado en la base de datos",
                            errorType: 'invalid_id',
                            errorValue: $value,
                            originalData: json_encode($row),
                        );




                        $pipeline->sadd("invalid_rows:{$this->batchId}", $rowNumber);
                        Log::debug("Fila $rowNumber marcada como inválida por ID $value en campo $fieldName");
                    }
                }
            }
        });

        $pipeline->execute();
        Redis::connection('redis_6380')->expire("import_errors:{$this->batchId}", 3600);
        Redis::connection('redis_6380')->expire("invalid_rows:{$this->batchId}", 3600);

        $invalidCount = Redis::connection('redis_6380')->scard("invalid_rows:{$this->batchId}");
        Log::info("Total de filas inválidas registradas en Redis: $invalidCount");
    }

    protected function getRedisMasterKey(string $modelClass): string
    {
        $modelName = strtolower(class_basename($modelClass));
        // log::info("getRedisMasterKey", ["{$modelName}_fields_master"]);
        return "{$modelName}_fields_master";
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

        Redis::connection('redis_6380')->rpush("import_errors:{$this->batchId}", json_encode($error));
        Redis::connection('redis_6380')->expire("import_errors:{$this->batchId}", 3600);
    }

    public function getErrors(): array
    {
        $rawErrors = Redis::connection('redis_6380')->lrange("import_errors:{$this->batchId}", 0, -1);
        $errors = [];
        foreach ($rawErrors as $errorJson) {
            $errors[] = json_decode($errorJson, true);
        }

        return $errors;
    }

    function validarFacturaIdsEnReconciliationGroupInvoice(array $csvInvoiceIds, string $reconciliation_group_id): void
    {
        // Obtener IDs de la base de datos
        $dbInvoiceIds = ReconciliationGroupInvoice::select(["id","reconciliation_group_id","invoice_audit_id"])
            ->where("reconciliation_group_id", $reconciliation_group_id)
            ->get()
            ->pluck("invoice_audit_id")
            ->toArray();
        Log::info("dbInvoiceIds", [$csvInvoiceIds]);
        Log::info("dbInvoiceIds", [$dbInvoiceIds]);
        Log::info("reconciliation_group_id", [$reconciliation_group_id]);


        // Convertir ambos arrays a valores únicos para evitar duplicados
        $csvIds = array_unique($csvInvoiceIds);
        $dbIds = array_unique($dbInvoiceIds);

        // Caso 1: Si el CSV está vacío
        if (empty($csvIds)) {

            $this->addError(
                0,
                'FACTURA_ID',
                'La columna FACTURA_ID del CSV está vacía o no contiene IDs válidos',
                'empty_factura_id',
                '',
                json_encode([])
            );
            return;
        }

        // Caso 2: Si hay IDs en el CSV que no existen en la BD (validación específica)
        $missingIds = array_diff($csvIds, $dbIds);

        if (!empty($missingIds)) {

            // Agregar error detallado para cada ID faltante
            foreach ($missingIds as $missingId) {
                $this->addError(
                    "", // Fila vacia para errores generales de validación
                    'FACTURA_ID',
                    "El ID de factura '$missingId' no existe en este grupo de conciliación",
                    'invalid_factura_id',
                    $missingId,
                    json_encode(['factura_id' => $missingId])
                );
            }

            // Log adicional opcional
            Log::warning("Se encontraron " . count($missingIds) . " ID(s) de FACTURA_ID inválidos");
        }
    }
}
