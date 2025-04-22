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
use App\Models\AssignmentBatche;
use App\Models\InvoiceAudit;
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

class AssingmentImport implements ShouldQueue, ToModel, WithChunkReading, WithEvents, WithCustomCsvSettings
{
    // ... constructor y otras propiedades ...

    private $key_redis_project;
    private $cacheService;

    public function __construct(
        protected $user_id,
        protected $company_id,
        protected $assignmentBatches,
        protected $users,
        protected $auditUsers,
        protected $invoiceAudits,
        protected $assignmentStatusEnumValues,
        protected $file_path,
    ) {
        $this->cacheService = new CacheService();

        $this->key_redis_project = env('KEY_REDIS_PROJECT');
    }

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function (BeforeImport $event) {
                // Limpiar errores
                Redis::del("list:assignment_import_errors_{$this->user_id}");

                // Obtener total de filas (ajusta si hay encabezados)
                $totalRows = $event->getReader()->getTotalRows()['Worksheet'];
                $totalRows = max($totalRows, 1);

                Redis::set("integer:assignments_import_total_{$this->user_id}", $totalRows);
                Redis::set("integer:assignments_import_processed_{$this->user_id}", 0);
            },
            AfterImport::class => function (AfterImport $event) {
                // Limpiar cache al finalizar
                $this->cacheService->clearByPrefix($this->key_redis_project . 'string:assignments*');

                Redis::del("integer:assignments_import_total_{$this->user_id}");
                Redis::del("integer:assignments_import_processed_{$this->user_id}");

                // Recuperar y mostrar los errores almacenados en Redis
                $errorListKey = "list:assignment_import_errors_{$this->user_id}";
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
                    $nameFile = 'error_' . $this->user_id . '.json';
                    $routeJson = 'companies/company_' . $this->company_id . '/assignment/errors/' . $nameFile; // Ruta donde se guardará la carpeta
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

                $this->cacheService->clearByPrefix($this->key_redis_project . 'string:assignments_paginate_count_all_data*');
                $this->cacheService->clearByPrefix($this->key_redis_project . 'string:invoice_audits_paginateThirds*');
                $this->cacheService->clearByPrefix($this->key_redis_project . 'string:invoice_audits_paginateBatche*');
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
        if ($this->validations($row, $processed, $data)) {
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

        if ($assignmentBatch == null) { // Usar === para comparación estricta

            $errorData = [
                'column' => '1',
                'row' => $processed,
                'value' => $row[0],
                'data' => $data, // Cambié $data por $row ya que $data no está definida aquí
                'errors' => 'El ID del paquete no existe en la base de datos.',
            ];
            Redis::rpush("list:assignment_import_errors_{$this->user_id}", json_encode($errorData));
            $error = true; // O lanza una excepción, o haz algo para detener el flujo

        }

        $user = $this->user($row[1], 'id');

        if ($user == null) { // Usar === para comparación estricta

            $errorData = [
                'column' => '2',
                'row' => $processed,
                'value' => $row[1],
                'data' => $data, // Cambié $data por $row ya que $data no está definida aquí
                'errors' => 'El ID del usuario no existe en la base de datos.',
            ];
            Redis::rpush("list:assignment_import_errors_{$this->user_id}", json_encode($errorData));
            $error = true; // O lanza una excepción, o haz algo para detener el flujo

        }

        $auditUser = $this->auditUser($row[1], 'id');

        if ($auditUser == null) { // Usar === para comparación estricta

            $errorData = [
                'column' => '2',
                'row' => $processed,
                'value' => $row[1],
                'data' => $data, // Cambié $data por $row ya que $data no está definida aquí
                'errors' => 'El usuario no cuenta con la funcion de rol Auditor.',
            ];
            Redis::rpush("list:assignment_import_errors_{$this->user_id}", json_encode($errorData));
            $error = true; // O lanza una excepción, o haz algo para detener el flujo

        }

        $invoiceAudit = $this->invoiceAudit($row[2], 'id');

        if ($invoiceAudit == null) { // Usar === para comparación estricta

            $errorData = [
                'column' => '3',
                'row' => $processed,
                'value' => $row[2],
                'data' => $data, // Cambié $data por $row ya que $data no está definida aquí
                'errors' => 'El ID de la factura no existe en la base de datos.',
            ];
            Redis::rpush("list:assignment_import_errors_{$this->user_id}", json_encode($errorData));
            $error = true; // O lanza una excepción, o haz algo para detener el flujo

        }

        if (!in_array($row[4], $this->assignmentStatusEnumValues, true)) {
            $errorData = [
                'column' => '5',
                'row' => $processed,
                'value' => $row[4],
                'data' => $data,
                'errors' => 'El codigo del estado no coincide con los estados del sistema.',
            ];
            Redis::rpush("list:assignment_import_errors_{$this->user_id}", json_encode($errorData));
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
        $redisData = $this->invoiceAudits;

        $cache = $redisData;

        $data = $cache->first(function ($item) use ($value, $field) {
            $match = isset($item[$field]) && strtoupper($item[$field]) === strtoupper($value);
            return $match;
        });

        return $data;
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
                        "name" => $user->full_name,
                        "email" => $user->email,
                    ]
                ],
                subject: $data['title'],
                templateId: 10,  // El ID de la plantilla de Brevo que quieres usar
                params: [
                    "full_name" => $user->full_name,
                    "subtitle" => $data['subtitle'],
                    "bussines_name" => $user->company?->name,
                    "assignments_import" => $data['assignments_import'],
                    "show_table_errors" => count($data['assignments_import']) > 0 ? true : false,
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
