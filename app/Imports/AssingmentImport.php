<?php

namespace App\Imports;

// app/Imports/AssingmentImport.php

use App\Enums\Assignment\StatusAssignmentEnum;
use App\Events\ModalError;
use App\Events\ProgressCircular;
use App\Exports\Assignment\AssignmentExcelErrorsValidationExport;
use App\Helpers\Constants;
use App\Jobs\BrevoProcessSendEmail;
use App\Models\Assignment;
use App\Models\User;
use App\Notifications\BellNotification;
use App\Services\CacheService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Facades\Excel;

class AssingmentImport implements ShouldQueue, ToModel, WithChunkReading, WithCustomCsvSettings, WithEvents
{
    // ... constructor y otras propiedades ...

    private $key_redis_project;

    private $cacheService;

    private $assignments;

    private $invoice_audits;

    private $keyErrorRedis;

    public function __construct(
        protected $user_id,
        protected $company_id,
        protected $assignmentBatches,
        protected $users,
        protected $auditUsers,
        protected $assignmentStatusEnumValues,
    ) {
        $this->cacheService = new CacheService;

        $this->key_redis_project = env('KEY_REDIS_PROJECT');
        $this->keyErrorRedis = "string:assignment_import_errors_{$this->user_id}";
        $this->assignments = [];
        $this->invoice_audits = [];
    }

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function (BeforeImport $event) {
                // Limpiar errores
                Redis::del($this->keyErrorRedis);
                Redis::del("set:invoice_audit_validated_{$this->user_id}");

                // Obtener total de filas (ajusta si hay encabezados)
                $totalRows = $event->getReader()->getTotalRows()['Worksheet'];
                $totalRows = max($totalRows, 1);

                Redis::set("integer:assignments_import_total_{$this->user_id}", $totalRows);
                Redis::set("integer:assignments_import_processed_{$this->user_id}", 0);

                $keyData = "assignments:company_{$this->company_id}:cronjob_";
                $this->assignments = getCronjobHashes($keyData);

                $keyData = "invoice_audits:company_{$this->company_id}:cronjob_";
                $this->invoice_audits = getCronjobHashes($keyData);
            },
            AfterImport::class => function (AfterImport $event) {
                // Limpiar cache al finalizar
                $this->cacheService->clearByPrefix($this->key_redis_project.'string:assignments*');

                Redis::del("integer:assignments_import_total_{$this->user_id}");
                Redis::del("integer:assignments_import_processed_{$this->user_id}");

                // Recuperar y mostrar los errores almacenados en Redis
                $errorListKey = $this->keyErrorRedis;
                $errors = Redis::lrange($errorListKey, 0, -1); // Obtener todos los elementos de la lista
                $errorsFormatted = [];

                if (! empty($errors)) {
                    // logger('Errores encontrados durante la importación:');
                    foreach ($errors as $index => $errorJson) {
                        $errorsFormatted[] = json_decode($errorJson, true); // Decodificar el JSON
                        // logger("Error #" . ($index + 1) . ": " . json_encode($errorData));
                    }
                } else {
                    // logger('No se encontraron errores durante la importación.');
                }

                // Convert array to JSON
                $routeJson = null;
                if (count($errorsFormatted) > 0) {
                    $nameFile = 'error_'.$this->user_id.'.json';
                    $routeJson = 'companies/company_'.$this->company_id.'/assignment/errors/'.$nameFile; // Ruta donde se guardará la carpeta
                    Storage::disk(Constants::DISK_FILES)->put($routeJson, json_encode($errorsFormatted, JSON_PRETTY_PRINT));
                }

                // Emitir errores al front
                ModalError::dispatch("assignmentModalErrors.{$this->user_id}", $routeJson);

                // Enviar notificación al usuario
                $title = 'Importación de asignaciones';
                $subtitle = 'La importación de asignaciones ha finalizado sin novedad.';
                if (count($errorsFormatted) > 0) {
                    $subtitle = 'La importación de asignaciones ha finalizado con las siguientes novedades:';
                }

                $this->sendNotification(
                    $this->user_id,
                    [
                        'title' => $title,
                        'subtitle' => $subtitle,
                        'assignments_import' => $errorsFormatted,
                    ]
                );

                $this->cacheService->clearByPrefix($this->key_redis_project.'string:assignments_paginate_count_all_data*');
                $this->cacheService->clearByPrefix($this->key_redis_project.'string:invoice_audits_paginateThirds*');
                $this->cacheService->clearByPrefix($this->key_redis_project.'string:invoice_audits_paginateBatche*');

                // Artisan::call('redis:run-service-job');
            },
        ];
    }

    public function model(array $row)
    {
        // Incrementar contador y calcular progreso

        $processed = Redis::incrby("integer:assignments_import_processed_{$this->user_id}", 1);
        $total = Redis::get("integer:assignments_import_total_{$this->user_id}") ?: 1;
        $progress = ($processed / $total) * 100;

        $data = [
            'assignment_batch_id' => $row[0],
            'user_id' => $row[1],
            'invoice_audit_id' => $row[2],
            'phase' => $row[3],
            'status' => $row[4],
            'company_id' => $this->company_id,
        ];

        // Validar los datos manualmente

        $a = $this->validations($row, $processed, $data);

        if ($a === true) {
            // Emitir evento de progreso
            ProgressCircular::dispatch("assignment.{$this->user_id}", $progress);

            return null; // Si hay errores, omitir esta fila
        }

        Assignment::create($data);

        // Emitir evento de progreso
        ProgressCircular::dispatch("assignment.{$this->user_id}", $progress);

        return null;
    }

    public function chunkSize(): int
    {
        return Constants::CHUNKSIZE; // Aumenta este valor para mejor rendimiento
    }

    public function validations($row, $processed, $data)
    {
        $error = false;

        // Guardar los errores en Redis como una lista
        $assignmentBatch = $this->assignmentBatch($row[0], 'id');
        if ($assignmentBatch == null) {
            $this->logError('1', $processed, $row[0], $data, 'El ID del paquete no existe en la base de datos.');
            $error = true;

        }

        $user = $this->user($row[1], 'id');
        if ($user == null) {

            $this->logError('2', $processed, $row[1], $data, 'El ID del usuario no existe en la base de datos.');
            $error = true;

        }

        $auditUser = $this->auditUser($row[1], 'id');
        if ($auditUser == null) {

            $this->logError('2', $processed, $row[1], $data, 'El usuario no cuenta con la funcion de rol Auditor.');
            $error = true;

        }

        $invoiceAudit = $this->invoiceAudit($row[2], 'id');
        if ($invoiceAudit) {

            $this->logError('3', $processed, $row[2], $data, 'El ID de la factura no existe en la base de datos.');
            $error = true;

        }

        $assignment = $this->assignment($row[2], 'invoice_audit_id');
        if ($assignment) {

            $this->logError('3', $processed, $row[2], $data, "La factura {$row[2]} ya cuenta con una asignacion registrada en BD.");
            $error = true;

        }

        $assignment_in_file = $this->assignmentInFile($row[2]);
        if ($assignment_in_file) {
            $this->logError('3', $processed, $row[2], $data, "La factura '{$row[2]}' ya está registrado en el archivo actual.");
            $error = true;
        }

        if (! in_array($row[4], $this->assignmentStatusEnumValues, true)) {
            $this->logError('5', $processed, $row[4], $data, 'El codigo del estado no coincide con los estados del sistema.');
            $error = true;
        }

        return $error; // Omitir esta fila
    }

    public function assignmentBatch($value, $field)
    {
        $redisData = $this->assignmentBatches;

        $cache = $redisData;

        $data = $cache->first(function ($item) use ($value, $field) {
            $match = isset($item[$field]) && strtoupper($item[$field]) === strtoupper($value);

            return $match;
        });

        return $data;
    }

    public function user($value, $field)
    {
        $redisData = $this->users;

        $cache = $redisData;

        $data = $cache->first(function ($item) use ($value, $field) {
            $match = isset($item[$field]) && strtoupper($item[$field]) === strtoupper($value);

            return $match;
        });

        return $data;
    }

    public function auditUser($value, $field)
    {
        $redisData = $this->auditUsers;

        $cache = $redisData;

        $data = $cache->first(function ($item) use ($value, $field) {
            $match = isset($item[$field]) && strtoupper($item[$field]) === strtoupper($value);

            return $match;
        });

        return $data;
    }

    public function invoiceAudit($value, $field)
    {
        $redisData = $this->invoice_audits;

        $cache = collect($redisData);

        $data = $cache->first(function ($item) use ($value, $field) {
            $match = isset($item[$field]) && strtoupper($item[$field]) === strtoupper($value);

            return $match;
        });

        return $data ? false : true;
    }

    public function assignment($value, $field)
    {
        $redisData = $this->assignments;

        $cache = collect($redisData);

        $data = $cache->first(function ($item) use ($value, $field) {
            $match = isset($item[$field]) && strtoupper($item[$field]) === strtoupper($value) && strtoupper($item['status']) !== strtoupper(StatusAssignmentEnum::ASSIGNMENT_EST_003->value);

            return $match;
        });

        return $data ? true : false;
    }

    public function assignmentInFile($value)
    {
        $setKey = "set:invoice_audit_validated_{$this->user_id}";

        $error = false;

        // Verificar si el valor ya existe en el conjunto
        if (! Redis::sismember($setKey, $value)) {
            // El valor no existe, agregarlo al conjunto
            Redis::sadd($setKey, $value);
        } else {
            $error = true;
        }

        return $error;
    }

    /**
     * Registrar un error en Redis.
     */
    private function logError($column, $row, $value, $data, $errorMessage)
    {
        $errorData = [
            'column' => $column,
            'row' => $row,
            'value' => $value,
            'data' => $data,
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

            $excel = $this->excelErrorsValidation($data['assignments_import']);
            $csv = $this->exportCsvErrorsValidation($data['assignments_import']);

            // Enviar el correo usando el job de Brevo
            BrevoProcessSendEmail::dispatch(
                emailTo: [
                    [
                        'name' => $user->full_name,
                        'email' => $user->email,
                    ],
                ],
                subject: $data['title'],
                templateId: 10,  // El ID de la plantilla de Brevo que quieres usar
                params: [
                    'full_name' => $user->full_name,
                    'subtitle' => $data['subtitle'],
                    'bussines_name' => $user->company?->name,
                    'assignments_import' => $data['assignments_import'],
                    'show_table_errors' => count($data['assignments_import']) > 0 ? true : false,
                ],
                attachments: [
                    [
                        'name' => 'Lista de errores de validación.xlsx',
                        'content' => base64_encode($excel),
                    ],
                    [
                        'name' => 'Asignaciones.csv',
                        'content' => base64_encode($csv),
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
