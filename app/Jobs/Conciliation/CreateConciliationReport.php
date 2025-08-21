<?php

namespace App\Jobs\Conciliation;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use App\Models\User;
use App\Notifications\BellNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Helpers\Constants;
use Throwable;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\Conciliation\ConciliationGenerateConciliationReportExcelExport;
use App\Models\ConciliationResult;
use App\Repositories\ConciliationReportRepository;
use App\Repositories\ReconciliationGroupRepository;
use Illuminate\Support\Facades\Redis;

class CreateConciliationReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $request;
    protected $userId;
    protected $fileName;
    protected $reconciliationGroupId;
    protected $processId;

    public function __construct($request, $userId, $fileName, $reconciliationGroupId)
    {
        $this->request = $request;
        $this->userId = $userId;
        $this->fileName = $fileName;
        $this->reconciliationGroupId = $reconciliationGroupId;
        $this->processId = uniqid('report_', true);
    }

    public function handle()
    {
        try {
            // Obtener el reconciliation group
            $reconciliationGroupRepository = app(ReconciliationGroupRepository::class);
            $reconciliationGroup = $reconciliationGroupRepository->find(
                id: $this->reconciliationGroupId,
                select: ["id", "third_id"]
            );

            if (!$reconciliationGroup) {
                throw new \Exception("No se encontró el grupo de conciliación");
            }

            // Obtener el conteo TOTAL de conciliation results
            $totalCount = ConciliationResult::where("reconciliation_group_id", $this->reconciliationGroupId)->count();

            if ($totalCount === 0) {
                throw new \Exception("No se encontraron resultados de conciliación para procesar");
            }

            // Preparar Redis - Claves ÚNICAS por usuario y proceso
            $invoicesKey = "conciliation:{$this->reconciliationGroupId}:{$this->userId}:{$this->processId}:invoices";
            $totalsKey = "conciliation:{$this->reconciliationGroupId}:{$this->userId}:{$this->processId}:totals";
            $processKey = "conciliation:{$this->reconciliationGroupId}:{$this->userId}:{$this->processId}:status";

            // Inicializar Redis
            $redis = Redis::connection();

            // Guardar metadata del proceso
            $redis->hmset($processKey, [
                'user_id' => $this->userId,
                'file_name' => $this->fileName,
                'started_at' => now()->toISOString(),
                'total_records' => $totalCount
            ]);

            // Inicializar estructura en Redis
            $redis->del([$invoicesKey, $totalsKey]);
            $redis->hmset($totalsKey, [
                'total_value' => 0,
                'initial_gloss_value' => 0,
                'accepted_value_eps' => 0,
                'accepted_value_ips' => 0,
                'ratified_value' => 0
            ]);

            // Expirar en 24 horas
            $redis->expire($invoicesKey, 86400);
            $redis->expire($totalsKey, 86400);
            $redis->expire($processKey, 86400);

            $chunkSize = 1000;
            $chunks = ceil($totalCount / $chunkSize);

            // Crear batch de jobs
            $jobs = [];
            for ($i = 0; $i < $chunks; $i++) {
                $offset = $i * $chunkSize;

                $jobs[] = new ProcessConciliationReportChunk(
                    $this->reconciliationGroupId,
                    $offset,
                    $chunkSize,
                    $invoicesKey,
                    $totalsKey,
                    $this->processId,
                    $this->userId
                );
            }

            // Variables locales para usar en los closures (EVITAR $this->)
            $userId = $this->userId;
            $reconciliationGroupId = $this->reconciliationGroupId;
            $processId = $this->processId;
            $fileName = $this->fileName;
            $requestData = $this->request;

            // Ejecutar batch
            $batch = Bus::batch($jobs)
                ->name("conciliation_report_export_{$processId}")
                ->onqueue('download_files')
                ->catch(function (Throwable $e) use ($userId, $reconciliationGroupId, $processId) {
                    Log::error('Error en el batch de conciliación: ' . $e->getMessage());

                    // Limpiar Redis en caso de error
                    self::cleanupRedisStatic($reconciliationGroupId, $userId, $processId);

                    // Notificar error al usuario
                    $user = User::find($userId);
                    if ($user) {
                        $user->notify(new BellNotification([
                            'title' => "Error al generar reporte de conciliación",
                            'subtitle' => "Ocurrió un error durante la generación del reporte",
                            'type' => 'error'
                        ]));
                    }
                })
                ->then(function () use ($invoicesKey, $totalsKey, $userId, $reconciliationGroupId, $processId, $fileName, $requestData) {
                    self::generateFinalReportStatic(
                        $invoicesKey,
                        $totalsKey,
                        $userId,
                        $reconciliationGroupId,
                        $processId,
                        $fileName,
                        $requestData
                    );
                })
                ->dispatch();

            return $batch;

        } catch (\Exception $e) {
            Log::error('Error en CreateConciliationReport: ' . $e->getMessage());

            // Limpiar Redis en caso de error
            self::cleanupRedisStatic($this->reconciliationGroupId, $this->userId, $this->processId);

            // Notificar error al usuario
            $user = User::find($this->userId);
            if ($user) {
                $user->notify(new BellNotification([
                    'title' => "Error al generar reporte de conciliación",
                    'subtitle' => $e->getMessage(),
                    'type' => 'error'
                ]));
            }
        }
    }

    // Métodos estáticos para evitar problemas de serialización
    public static function cleanupRedisStatic($reconciliationGroupId, $userId, $processId)
    {
        try {
            $redis = Redis::connection();
            // Patrón para limpiar todas las keys de este proceso
            $pattern = "conciliation:{$reconciliationGroupId}:{$userId}:{$processId}:*";

            $keys = $redis->keys($pattern);
            if (!empty($keys)) {
                $redis->del($keys);
            }
        } catch (\Exception $e) {
            Log::error('Error cleaning up Redis: ' . $e->getMessage());
        }
    }

    public static function generateFinalReportStatic($invoicesKey, $totalsKey, $userId, $reconciliationGroupId, $processId, $fileName, $requestData)
    {
        try {
            $redis = Redis::connection();

            // Obtener datos de Redis
            $totals = $redis->hgetall($totalsKey);

            // Convertir a float
            foreach ($totals as $key => $value) {
                $totals[$key] = (float) $value;
            }

            // Obtener datos adicionales necesarios para el reporte
            $conciliationReport = app(ConciliationReportRepository::class)->searchOne([
                "reconciliation_group_id" => $requestData["reconciliation_group_id"]
            ]);

            $reconciliationGroupRepository = app(ReconciliationGroupRepository::class);
            $reconciliationGroup = $reconciliationGroupRepository->find(
                id: $reconciliationGroupId,
                select: ["id", "third_id"]
            );

            $third = $reconciliationGroup->third;

            // Concatenar las modalidades separadas por comas
            $modalities = $third->invoiceAudits->pluck('modality')->unique()->implode(',');

            // Formatear la fecha actual en español
            $currentDate = Carbon::now();
            $currentDate->setLocale('es');
            $day = str_pad($currentDate->day, 2, '0', STR_PAD_LEFT);
            $month = $currentDate->monthName;
            $year = $currentDate->year;
            $formattedDateReport = "$day del mes de $month de $year";

            // Preparar datos para el Excel
            $data = [
                'modalities' => $modalities,
                'third' => [
                    'name' => $third->name,
                    'nit' => $third->nit,
                    'departament' => $third->departmentAndCity?->departamento,
                    'city' => $third->departmentAndCity?->municipio,
                ],
                'dateConciliation' => $conciliationReport->dateConciliation,
                'formattedDateReport' => $formattedDateReport,
                'totales' => [
                    'total_value' => formatNumber($totals['total_value'] ?? 0),
                    'initial_gloss_value' => formatNumber($totals['initial_gloss_value'] ?? 0),
                    'pending_value' => formatNumber(0),
                    'accepted_value_eps' => formatNumber($totals['accepted_value_eps'] ?? 0),
                    'accepted_value_ips' => formatNumber($totals['accepted_value_ips'] ?? 0),
                    'ratified_value' => formatNumber($totals['ratified_value'] ?? 0),
                ],
                'signatures' => [
                    'nameIPSrepresentative' => $conciliationReport->nameIPSrepresentative,
                    'positionIPSrepresentative' => $conciliationReport->positionIPSrepresentative,
                    'elaborator_full_name' => $conciliationReport->elaborator?->full_name,
                    'elaborator_position' => $conciliationReport->elaborator_position,
                    'reviewer_full_name' => $conciliationReport->reviewer?->full_name,
                    'reviewer_position' => $conciliationReport->reviewer_position,
                    'approver_full_name' => $conciliationReport->approver?->full_name,
                    'approver_position' => $conciliationReport->approver_position,
                    'legal_representative_full_name' => $conciliationReport->legal_representative?->full_name,
                    'legal_representative_position' => $conciliationReport->legal_representative_position,
                    'health_audit_director_full_name' => $conciliationReport->health_audit_director?->full_name,
                    'health_audit_director_position' => $conciliationReport->health_audit_director_position,
                    'vp_planning_control_full_name' => $conciliationReport->vp_planning_control?->full_name,
                    'vp_planning_control_position' => $conciliationReport->vp_planning_control_position,
                ],
                'invoices' => [],
                'redis_invoices_key' => $invoicesKey
            ];

            // Generar Excel
            $excel = Excel::raw(new ConciliationGenerateConciliationReportExcelExport($data), \Maatwebsite\Excel\Excel::XLSX);

            // Guardar archivo final
            $finalPath = 'conciliation_reports/' . $fileName;
            Storage::disk(Constants::DISK_FILES)->put($finalPath, $excel);

            // Limpiar Redis
            self::cleanupRedisStatic($reconciliationGroupId, $userId, $processId);

            // Obtener URL para descarga
            $absolutePath = env('SYSTEM_URL_BACK') . 'storage/' . $finalPath;

            // Notificar al usuario
            $user = User::find($userId);
            if ($user) {
                $user->notify(new BellNotification([
                    'title' => "Acta de conciliación generado con éxito",
                    'subtitle' => "Da click en la notificación para descargar",
                    'action_url' => $absolutePath,
                    'openInNewTab' => true,
                ]));
            }

        } catch (\Exception $e) {
            Log::error('Error al generar reporte final de conciliación: ' . $e->getMessage());

            // Limpiar Redis
            self::cleanupRedisStatic($reconciliationGroupId, $userId, $processId);

            // Notificar error al usuario
            $user = User::find($userId);
            if ($user) {
                $user->notify(new BellNotification([
                    'title' => "Error al generar reporte de conciliación",
                    'subtitle' => "Ocurrió un error durante la generación del reporte final",
                    'type' => 'error'
                ]));
            }
        }
    }

    // Método de instancia para uso interno
    protected function cleanupRedis()
    {
        self::cleanupRedisStatic($this->reconciliationGroupId, $this->userId, $this->processId);
    }
}
