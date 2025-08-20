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
use App\Repositories\ConciliationReportRepository;
use App\Repositories\ReconciliationGroupRepository;

class CreateConciliationReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $request;
    protected $userId;
    protected $fileName;
    protected $reconciliationGroupId;

    public function __construct($request, $userId, $fileName, $reconciliationGroupId)
    {
        $this->request = $request;
        $this->userId = $userId;
        $this->fileName = $fileName;
        $this->reconciliationGroupId = $reconciliationGroupId;
    }

    public function handle()
    {
        try {
            // Obtener el reconciliation group con las invoices
            $reconciliationGroupRepository = app(ReconciliationGroupRepository::class);
            $reconciliationGroup = $reconciliationGroupRepository->find(
                id: $this->reconciliationGroupId,
                with: ["invoices:id"],
                select: ["id", "third_id"]
            );

            if (!$reconciliationGroup) {
                throw new \Exception("No se encontró el grupo de conciliación");
            }

            // Obtener IDs de facturas
            $invoicesIds = $reconciliationGroup->invoices->pluck("id")->toArray();
            $totalCount = count($invoicesIds);

            if ($totalCount === 0) {
                throw new \Exception("No se encontraron facturas para procesar");
            }

            $chunkSize = 500; // Tamaño de cada chunk
            $chunks = ceil($totalCount / $chunkSize);
            $tempFileName = 'conciliation_report_' . now()->format('Ymd_His') . '.json';

            // Crear archivo temporal vacío
            Storage::disk(Constants::DISK_FILES)->put('temp/conciliation_reports/' . $tempFileName, json_encode([
                'invoices' => [],
                'totals' => [
                    'total_value' => 0,
                    'initial_gloss_value' => 0,
                    'accepted_value_eps' => 0,
                    'accepted_value_ips' => 0,
                    'ratified_value' => 0
                ]
            ]));

            // Crear batch de jobs
            $jobs = [];
            for ($i = 0; $i < $chunks; $i++) {
                $offset = $i * $chunkSize;

                $jobs[] = new ProcessConciliationReportChunk(
                    $invoicesIds,
                    $offset,
                    $chunkSize,
                    $tempFileName
                );
            }

            $fileName = $this->fileName;
            $userId = $this->userId;
            $requestData = $this->request;

            // Ejecutar batch
            $batch = Bus::batch($jobs)
                ->name('conciliation_report_export')
                ->onqueue('download_files')
                ->catch(function (Throwable $e) use ($userId) {
                    Log::error('Error en el batch de conciliación: ' . $e->getMessage());

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
                ->then(function () use ($tempFileName, $fileName, $userId, $requestData, $reconciliationGroup) {
                    // Cuando todos los chunks estén listos, generar el Excel final
                    try {
                        // Leer todos los datos procesados
                        $filePath = 'temp/conciliation_reports/' . $tempFileName;
                        $processedData = json_decode(Storage::disk(Constants::DISK_FILES)->get($filePath), true);

                        $invoicesData = $processedData['invoices'] ?? [];
                        $totals = $processedData['totals'] ?? [];

                        // Obtener datos adicionales necesarios para el reporte
                        $conciliationReport = app(ConciliationReportRepository::class)->searchOne([
                            "reconciliation_group_id" => $requestData["reconciliation_group_id"]
                        ]);
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
                            'invoices' => $invoicesData,
                        ];

                        Log::info("data", [$data]);
                        // Generar Excel
                        $excel = Excel::raw(new ConciliationGenerateConciliationReportExcelExport($data), \Maatwebsite\Excel\Excel::XLSX);

                        // Guardar archivo final
                        $finalPath = 'conciliation_reports/' . $fileName;
                        Storage::disk(Constants::DISK_FILES)->put($finalPath, $excel);

                        // Limpiar archivo temporal
                        Storage::disk(Constants::DISK_FILES)->delete($filePath);

                        // Obtener URL para descarga
                        $absolutePath = env('SYSTEM_URL_BACK') . 'storage/' . $finalPath;

                        // Notificar al usuario
                        $user = User::find($userId);
                        Log::info("userId",[$userId]);
                        Log::info("user",[$user]);
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
                })
                ->dispatch();

            return $batch;
        } catch (\Exception $e) {
            Log::error('Error en CreateConciliationReport: ' . $e->getMessage());

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

}
