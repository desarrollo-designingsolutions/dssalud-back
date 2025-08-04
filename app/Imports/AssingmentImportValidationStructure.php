<?php

namespace App\Imports;

use App\Events\ModalError;
use App\Events\ProgressCircular;
use App\Exports\Assignment\AssignmentExcelErrorsValidationExport;
use App\Helpers\Constants;
use App\Jobs\BrevoProcessSendEmail;
use App\Models\User;
use App\Notifications\BellNotification;
use App\Services\CacheService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Facades\Excel;

class AssingmentImportValidationStructure implements ShouldQueue, ToModel, WithChunkReading, WithCustomCsvSettings, WithEvents
{
    private $key_redis_project;

    private $cacheService;

    private $keyErrorRedis;

    private $channelLoading;

    public function __construct(
        protected $user_id,
        protected $company_id,
        protected $assignmentBatches,
        protected $users,
        protected $auditUsers,
        protected $assignmentStatusEnumValues,
        protected $file_path,
        protected $expectedColumns = 5,
    ) {
        $this->cacheService = new CacheService;

        $this->key_redis_project = env('KEY_REDIS_PROJECT');
        $this->keyErrorRedis = "string:assignment_import_errors_{$this->user_id}";
        $this->expectedColumns = $expectedColumns;
        $this->channelLoading = "csv_import_progress_assignment.{$this->user_id}";
    }

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function (BeforeImport $event) {
                // Limpiar errores
                Redis::del($this->keyErrorRedis);

                // Validar la existencia y legibilidad del archivo en el constructor

                $this->validateFile($event);
            },
            AfterImport::class => function (AfterImport $event) {
                // Limpiar cache al finalizar
                $this->cacheService->clearByPrefix($this->key_redis_project.'string:assignments*');

                Redis::del("integer:assignments_import_total_{$this->user_id}");
                Redis::del("integer:assignments_import_processed_{$this->user_id}");

                $dataErrors = $this->getErrorsRedis();

                if (count($dataErrors['errorsFormatted']) > 0) {
                    // Emitir errores al front
                    ModalError::dispatch("assignmentStructureModalErrors.{$this->user_id}", $dataErrors['routeJson']);

                    // Enviar notificación al usuario
                    $title = 'Importación de asignaciones';
                    $subtitle = 'Se encontraron errores en la estructura del archivo que esta intentando importar.';

                    $this->sendNotification(
                        $this->user_id,
                        [
                            'title' => $title,
                            'subtitle' => $subtitle,
                            'data_import' => $dataErrors['errorsFormatted'],
                        ]
                    );
                } else {
                    $fullFilePath = Storage::disk(Constants::DISK_FILES)->path($this->file_path);

                    $csv = Excel::import(new AssingmentImport($this->user_id, $this->company_id, $this->assignmentBatches, $this->users, $this->auditUsers, $this->assignmentStatusEnumValues), $fullFilePath);
                }
            },
        ];
    }

    public function model(array $row)
    {
        // Incrementar contador y calcular progreso
        $processed = Redis::incrby("integer:assignments_import_processed_{$this->user_id}", 1);
        $total = Redis::get("integer:assignments_import_total_{$this->user_id}") ?: 1;
        $progress = ($processed / $total) * 100;

        if ($this->validations($row, $processed)) {

            // Emitir evento de progreso
            ProgressCircular::dispatch($this->channelLoading, $progress, false);

            return null; // Si hay errores, omitir esta fila
        }

        // Emitir evento de progreso
        ProgressCircular::dispatch($this->channelLoading, $progress);

        return null;
    }

    public function chunkSize(): int
    {
        return Constants::CHUNKSIZE; // Aumenta este valor para mejor rendimiento
    }

    public function validations($row, $processed)
    {
        $error = false;

        $rowString = implode(';', array_map(function ($value) {
            // Reemplazar valores null con una cadena vacía para mantener el formato
            return $value === null ? '' : $value;
        }, $row));

        // Omitir filas vacías
        if (empty(array_filter($row))) {
            $this->logError(null, $processed, $rowString, 'La fila está vacía.');

            return true;
        }

        // Contar las columnas reales en la fila
        $numColumns = count(array_filter($row, function ($value) {
            return $value !== null;
        }));

        // Validar el número de columnas
        if ($numColumns !== $this->expectedColumns) {
            $this->logError(null, $processed, $rowString, "Se esperaban {$this->expectedColumns} columnas, pero se encontraron {$numColumns}.");
            $error = true;
        }

        return $error;
    }

    /**
     * Validar si el archivo existe, es legible y no está dañado.
     *
     * @throws \Exception Si el archivo es inválido o está dañado.
     */
    private function validateFile($event)
    {
        // Verificar si el archivo existe
        if (! Storage::disk(Constants::DISK_FILES)->exists($this->file_path)) {
            $this->logError('general', 0, null, "El archivo {$this->file_path} no existe.");
        }

        // Verificar si el archivo es legible
        try {
            $fileContent = Storage::disk(Constants::DISK_FILES)->get($this->file_path);
            if ($fileContent === null || $fileContent === '') {
                $this->logError('general', 0, null, "El archivo {$this->file_path} está vacío o no se puede leer.");
            }
        } catch (\Exception $e) {
            $this->logError('general', 0, null, "Error al leer el archivo {$this->file_path}: {$e->getMessage()}");
        }

        // Verificar la extensión del archivo
        $extension = pathinfo($this->file_path, PATHINFO_EXTENSION);
        if (strtolower($extension) !== 'csv') {
            $this->logError('general', 0, null, "El archivo {$this->file_path} no es un archivo CSV válido.");
        }

        // Intentar leer las primeras líneas para verificar si el archivo está corrupto
        try {
            $handle = fopen(Storage::disk(Constants::DISK_FILES)->path($this->file_path), 'r');
            if ($handle === false) {
                $this->logError('general', 0, null, "No se pudo abrir el archivo {$this->file_path}.");
            }

            // Leer la primera línea para verificar el formato
            $firstLine = fgetcsv($handle, 0, ';');
            fclose($handle);

            if ($firstLine === false || empty($firstLine)) {
                $this->logError('general', 0, null, "El archivo {$this->file_path} está corrupto o no tiene un formato CSV válido.");
            }
        } catch (\Exception $e) {
            $this->logError('general', 0, null, "Error al verificar el archivo {$this->file_path}: {$e->getMessage()}");
        }

        // Obtener total de filas (ajusta si hay encabezados)
        try {
            // Obtener total de filas (ajusta si hay encabezados)
            $totalRows = $event->getReader()->getTotalRows()['Worksheet'];
            $totalRows = max($totalRows, 1);

            Redis::set("integer:assignments_import_total_{$this->user_id}", $totalRows);
            Redis::set("integer:assignments_import_processed_{$this->user_id}", 0);
        } catch (\Exception $e) {
            $this->logError('general', 0, null, "Error al obtener el número de filas del archivo: {$e->getMessage()}");
        }

        $dataErrors = $this->getErrorsRedis();

        if (count($dataErrors['errorsFormatted']) > 0) {
            ProgressCircular::dispatch($this->channelLoading, 100, false);
            ModalError::dispatch("assignmentStructureModalErrors.{$this->user_id}", $dataErrors['routeJson']);
        }
    }

    private function getErrorsRedis()
    {
        $errorListKey = $this->keyErrorRedis;
        $errors = Redis::lrange($errorListKey, 0, -1); // Obtener todos los elementos de la lista
        $errorsFormatted = [];

        if (! empty($errors)) {
            // logger('Errores encontrados durante la importación:');
            foreach ($errors as $index => $errorJson) {
                $errorsFormatted[] = json_decode($errorJson, true); // Decodificar el JSON
            }
        } else {
            // logger('No se encontraron errores durante la importación.');
        }

        $routeJson = null;
        if (count($errorsFormatted) > 0) {
            $nameFile = 'error_'.$this->user_id.'.json';
            $routeJson = 'companies/company_'.$this->company_id."/assignments/import_temp/{$this->user_id}/".$nameFile; // Ruta donde se guardará la carpeta
            Storage::disk(Constants::DISK_FILES)->put($routeJson, json_encode($errorsFormatted, JSON_PRETTY_PRINT));
        }

        return [
            'errorsFormatted' => $errorsFormatted,
            'routeJson' => $routeJson,
        ];
    }

    /**
     * Registrar un error en Redis.
     */
    private function logError($column, $row, $value, $errorMessage)
    {
        $errorData = [
            'column' => $column,
            'row' => $row,
            'value' => $value,
            'error' => $errorMessage,
        ];
        Redis::rpush($this->keyErrorRedis, json_encode($errorData));
    }

    public function getCsvSettings(): array
    {
        return [
            'delimiter' => ';', // Configura el separador como punto y coma
            'input_encoding' => 'UTF-8', // Asegúrate de que la codificación sea correcta
        ];
    }

    private function sendNotification($userId, $data)
    {
        // Obtener el objeto User a partir del ID
        $user = User::find($userId);

        if ($user) {
            // Enviar notificación
            $user->notify(new BellNotification($data));

            $excel = $this->excelErrorsValidation($data['data_import']);

            // Enviar el correo usando el job de Brevo
            BrevoProcessSendEmail::dispatch(
                emailTo: [
                    [
                        'name' => $user->full_name,
                        'email' => $user->email,
                    ],
                ],
                subject: $data['title'],
                templateId: 11,  // El ID de la plantilla de Brevo que quieres usar
                params: [
                    'full_name' => $user->full_name,
                    'subtitle' => $data['subtitle'],
                    'bussines_name' => $user->company?->name,
                    'data_import' => $data['data_import'],
                    'show_table_errors' => count($data['data_import']) > 0 ? true : false,
                ],
                attachments: [
                    [
                        'name' => 'Lista de errores de validación.xlsx',
                        'content' => base64_encode($excel),
                    ],
                ],
            );
        }
    }

    private function excelErrorsValidation($data)
    {

        $excel = Excel::raw(new AssignmentExcelErrorsValidationExport($data), \Maatwebsite\Excel\Excel::XLSX);

        return $excel;
    }

    private function exportCsvErrorsValidation($data)
    {
        // Agrupar por 'row'
        $groupedErrors = collect($data)->groupBy('row');

        // Obtener un solo 'data' por grupo (el primero, por ejemplo)
        $result = $groupedErrors->map(function ($group) {
            // Tomar el primer elemento del grupo y devolver solo su 'data'
            return $group->first()['data'] ?? null;
        })->values();

        // Generar el CSV con Laravel Excel
        $csv = Excel::raw(new AssignmentExcelErrorsValidationExport($result), \Maatwebsite\Excel\Excel::CSV);

        return $csv;
    }
}
