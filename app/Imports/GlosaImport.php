<?php

namespace App\Imports;

use App\Events\ModalError;
use App\Events\ProgressCircular;
use App\Exports\Glosa\GlosaExcelErrorsValidationExport;
use App\Helpers\Constants;
use App\Jobs\BrevoProcessSendEmail;
use App\Jobs\Glosa\ProcessGlosasServiceJob;
use App\Models\Glosa;
use App\Models\User;
use App\Notifications\BellNotification;
use App\Services\CacheService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Facades\Excel;

class GlosaImport implements ShouldQueue, SkipsOnFailure, ToModel, WithChunkReading, WithCustomCsvSettings, WithEvents
{
    use Importable, SkipsFailures;

    public $services_id;

    private $key_redis_project;

    private $cacheService;

    public function __construct(
        protected $user_id,
        protected $company_id,
        protected $services,
        protected $users,
        protected $codeGlosas,
    ) {

        $this->cacheService = new CacheService;

        $this->key_redis_project = env('KEY_REDIS_PROJECT');
        $this->services_id = [];
        if (count($this->services->toArray()) > 0) {
            $this->services_id = $this->services->pluck('id')->toArray();
        }
    }

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function (BeforeImport $event) {
                // Limpiar errores
                Redis::del("list:glosas_import_errors_{$this->user_id}");

                // Obtener total de filas (ajusta si hay encabezados)
                $totalRows = $event->getReader()->getTotalRows()['Worksheet'];
                $totalRows = max($totalRows, 1);

                Redis::set("integer:glosas_import_total_{$this->user_id}", $totalRows);
                Redis::set("integer:glosas_import_processed_{$this->user_id}", 0);
            },
            AfterImport::class => function (AfterImport $event) {

                // Limpiar cache de Redis de las glosas
                $this->cacheService->clearByPrefix($this->key_redis_project.'string:glosas*');

                // Limpiar cache al finalizar
                Redis::del("integer:glosas_import_total_{$this->user_id}");
                Redis::del("integer:glosas_import_processed_{$this->user_id}");

                // Recuperar y mostrar los errores almacenados en Redis
                $errorListKey = "list:glosas_import_errors_{$this->user_id}";
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

                // logMessage($errorsFormatted);

                // Convert array to JSON
                $routeJson = null;
                if (count($errorsFormatted) > 0) {
                    $nameFile = 'error_'.$this->user_id.'.json';
                    $routeJson = 'companies/company_'.$this->company_id.'/glosas/errors/'.$nameFile; // Ruta donde se guardará la carpeta
                    Storage::disk(Constants::DISK_FILES)->put($routeJson, json_encode($errorsFormatted, JSON_PRETTY_PRINT));
                }

                // Emitir errores al front
                ModalError::dispatch("glosaModalErrors.{$this->user_id}", $routeJson);

                // Enviar notificación al usuario
                $title = 'Importación de glosas';
                $subtitle = 'La importación de glosas ha finalizado sin novedad.';
                if (count($errorsFormatted) > 0) {
                    $subtitle = 'La importación de glosas ha finalizado con las siguientes novedades:';
                }

                $this->sendNotification(
                    $this->user_id,
                    [
                        'title' => $title,
                        'subtitle' => $subtitle,
                        'glosas_import' => $errorsFormatted,
                    ]
                );

                // Obtener todos los service_id únicos como un array
                $uniqueServiceIds = Redis::smembers("set:glosas_service_ids_{$this->user_id}");

                // Convertir a colección si prefieres trabajar con Laravel Collections
                $uniqueServiceIdsCollection = collect($uniqueServiceIds);

                // Calcular el total de serviceId para el progreso
                $totalServices = $uniqueServiceIdsCollection->count();

                // Iterar sobre los serviceId y despachar jobs con el progreso
                $uniqueServiceIdsCollection->each(function ($serviceId, $index) use ($totalServices) {
                    // Calcular el progreso basado en la posición (index + 1 porque empieza en 0)
                    $progress = $totalServices > 0 ? (($index + 1) / $totalServices) * 100 : 100;

                    // Despachar el job pasando el user_id y el progreso
                    ProcessGlosasServiceJob::dispatch($serviceId, $this->user_id, $progress);
                });

                // $processedServices = 0;
                // foreach ($uniqueServiceIdsCollection as $serviceId) {
                //     ProcessGlosasServiceJob::dispatch($serviceId);
                // }

                // Opcional: Limpiar el Set de Redis después de usarlo
                Redis::del("set:glosas_service_ids_{$this->user_id}");
            },
        ];
    }

    public function model(array $row)
    {
        return DB::transaction(function () use ($row) {
            // Incrementar contador y calcular progreso
            $processed = Redis::incrby("integer:glosas_import_processed_{$this->user_id}", 1);
            $total = Redis::get("integer:glosas_import_total_{$this->user_id}") ?: 1;
            $progress = ($processed / $total) * 100;

            $data = [
                'user_id' => $row[0],
                'service_id' => $row[1],
                'code_glosa_id' => $row[2],
                'glosa_value' => $row[3],
                'observation' => $row[4],
                'company_id' => $this->company_id,
            ];

            // Validar los datos manualmente
            if ($this->validations($row, $processed, $data)) {
                // Emitir evento de progreso
                ProgressCircular::dispatch("glosa.{$this->user_id}", $progress);

                return null; // Si hay errores, omitir esta fila
            }

            Glosa::create($data);

            // Usar un Set de Redis para almacenar solo service_id únicos
            Redis::sadd("set:glosas_service_ids_{$this->user_id}", $row[1]);

            // Emitir evento de progreso
            ProgressCircular::dispatch("glosa.{$this->user_id}", $progress);

            return null;
        });
    }

    public function chunkSize(): int
    {
        return Constants::CHUNKSIZE;
    }

    public function validations($row, $processed, $data)
    {
        $error = false;

        if (in_array($row[1], $this->services_id) == false) {
            $errorData = [
                'column' => '2',
                'row' => $processed,
                'value' => $row[1],
                'data' => $data,
                'errors' => 'El ID del servicio no es valido para la carga actual.',
            ];
            Redis::rpush("list:glosas_import_errors_{$this->user_id}", json_encode($errorData));
            $error = true; // O lanza una excepción, o haz algo para detener el flujo
        }

        // Guardar los errores en Redis como una lista
        $result = $this->user($row[0], 'id');
        if ($result == null) { // Usar === para comparación estricta

            $errorData = [
                'column' => '1',
                'row' => $processed,
                'value' => $row[0],
                'data' => $data, // Cambié $data por $row ya que $data no está definida aquí
                'errors' => 'El ID de usuario no existe en la base de datos.',
            ];
            Redis::rpush("list:glosas_import_errors_{$this->user_id}", json_encode($errorData));
            $error = true; // O lanza una excepción, o haz algo para detener el flujo

        }

        if ($row[0] != $this->user_id) {
            $errorData = [
                'column' => '1',
                'row' => $processed,
                'value' => $row[0],
                'data' => $data, // Cambié $data por $row ya que $data no está definida aquí
                'errors' => 'El usuario que realiza la carga no corresponde al usuario registrado en el archivo CSV.',
            ];
            Redis::rpush("list:glosas_import_errors_{$this->user_id}", json_encode($errorData));
            $error = true; // O lanza una excepción, o haz algo para detener el flujo
        }

        $service = $this->service($row[1], 'id');
        if ($service == null) {
            $errorData = [
                'column' => '2', // Número de columna
                'row' => $processed, // Número de fila
                'value' => $row[1],
                'data' => $data,     // Datos originales
                'errors' => 'El ID del servicio no existe en la base de datos.', // Mensajes de error
            ];

            Redis::rpush("list:glosas_import_errors_{$this->user_id}", json_encode($errorData));
            $error = true; // O lanza una excepción, o haz algo para detener el flujo

        } else {
            if (is_numeric($row[3]) && $row[3] > $service['total_value']) {
                $errorData = [
                    'column' => '4', // Número de columna
                    'row' => $processed, // Número de fila
                    'value' => $row[3],
                    'data' => $data,     // Datos originales
                    'errors' => 'El valor de la glosa no puede supérar el valor del servicio.', // Mensajes de error
                ];

                Redis::rpush("list:glosas_import_errors_{$this->user_id}", json_encode($errorData));
                $error = true; // O lanza una excepción, o haz algo para detener el flujo

            }
        }

        if ($this->codeGlosa($row[2], 'id') == null) {
            $errorData = [
                'column' => '3', // Número de columna
                'row' => $processed, // Número de fila
                'value' => $row[2],
                'data' => $data,     // Datos originales
                'errors' => 'El ID del código de glosa no existe en la base de datos.', // Mensajes de error
            ];

            Redis::rpush("list:glosas_import_errors_{$this->user_id}", json_encode($errorData));
            $error = true; // O lanza una excepción, o haz algo para detener el flujo

        }

        // Validar que glosa_value sea un número válido
        if (! is_numeric($row[3]) || preg_match('/[a-zA-Z]/', $row[3])) {
            $errorData = [
                'column' => '4',
                'row' => $processed,
                'value' => $row[3],
                'data' => $data,
                'errors' => 'El valor de la glosa debe ser un número válido sin letras.',
            ];
            Redis::rpush("list:glosas_import_errors_{$this->user_id}", json_encode($errorData));
            $error = true;
        }

        return $error; // Omitir esta fila
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

    public function service($value, $field)
    {
        $normalizedValue = strtoupper($value);
        $key = "services:{$normalizedValue}";

        // Verificamos si el hash existe
        if (Redis::exists($key)) {
            // Recuperamos todos los datos del hash
            $serviceData = Redis::hgetall($key);

            return $serviceData ?: true; // Devuelve el hash completo o true si está vacío
        }

        return false; // No existe el hash
    }

    public function codeGlosa($value, $field)
    {
        $redisData = $this->codeGlosas;
        $cache = $redisData;

        $data = $cache->first(function ($item) use ($value, $field) {
            return strtoupper($item[$field]) === strtoupper($value);
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

    /**
     * Enviar notificación al usuario
     */
    private function sendNotification($userId, $data)
    {
        // Obtener el objeto User a partir del ID
        $user = User::find($userId);

        if ($user) {
            // Enviar notificación
            $user->notify(new BellNotification($data));

            $excel = $this->excelErrorsValidation($data['glosas_import']);
            $csv = $this->exportCsvErrorsValidation($data['glosas_import']);

            // Enviar el correo usando el job de Brevo
            BrevoProcessSendEmail::dispatch(
                emailTo: [
                    [
                        'name' => $user->full_name,
                        'email' => $user->email,
                    ],
                ],
                subject: $data['title'],
                templateId: 8,  // El ID de la plantilla de Brevo que quieres usar
                params: [
                    'full_name' => $user->full_name,
                    'subtitle' => $data['subtitle'],
                    'bussines_name' => $user->company?->name,
                    'glosas_import' => $data['glosas_import'],
                    'show_table_errors' => count($data['glosas_import']) > 0 ? true : false,
                ],
                attachments: [
                    [
                        'name' => 'Lista de errores de validación.xlsx',
                        'content' => base64_encode($excel),
                    ],
                    [
                        'name' => 'Glosas.csv',
                        'content' => base64_encode($csv),
                    ],
                ],
            );
        }
    }

    private function excelErrorsValidation($data)
    {

        $excel = Excel::raw(new GlosaExcelErrorsValidationExport($data), \Maatwebsite\Excel\Excel::XLSX);

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
        $csv = Excel::raw(new GlosaExcelErrorsValidationExport($result), \Maatwebsite\Excel\Excel::CSV);

        return $csv;
    }
}
