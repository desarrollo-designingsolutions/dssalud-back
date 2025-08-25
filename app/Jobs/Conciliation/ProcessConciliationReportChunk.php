<?php

namespace App\Jobs\Conciliation;

use App\Helpers\Constants;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\ConciliationResult;
use App\Services\CacheService;
use Illuminate\Support\Facades\Redis;

class ProcessConciliationReportChunk implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $reconciliationGroupId;
    protected $offset;
    protected $limit;
    protected $invoicesKey;
    protected $totalsKey;
    protected $processId;
    protected $userId;

    public function __construct($reconciliationGroupId, $offset, $limit, $invoicesKey, $totalsKey, $processId, $userId)
    {
        $this->reconciliationGroupId = $reconciliationGroupId;
        $this->offset = $offset;
        $this->limit = $limit;
        $this->invoicesKey = $invoicesKey;
        $this->totalsKey = $totalsKey;
        $this->processId = $processId;
        $this->userId = $userId;
    }

    public function handle()
    {
        if ($this->batch()->cancelled()) {
            return;
        }

        try {
            // Obtener resultados
            $request = [
                "reconciliationGroupId" => $this->reconciliationGroupId,
                "offset" => $this->offset,
                "limit" => $this->limit,
            ];
            $cacheService = new CacheService();
            $cacheKey = $cacheService->generateKey("ConciliationResult_donloadFile:", $request, 'string');
            $results= $cacheService->remember($cacheKey, function () {
                return ConciliationResult::where("reconciliation_group_id", $this->reconciliationGroupId)
                    ->with([
                        'invoiceAudit',
                        'invoiceAudit.auditoryFinalReport',
                        'invoiceAudit.third.departmentAndCity'
                    ])
                    ->offset($this->offset)
                    ->limit($this->limit)
                    ->get();
            }, Constants::REDIS_TTL);

            // Crear nueva conexión Redis en cada job
            $redis = Redis::connection();

            // Usar pipeline para múltiples operaciones Redis
            $pipe = $redis->pipeline();

            foreach ($results as $result) {
                $totalValue = $result->invoiceAudit?->total_value ?? 0;
                $initialGlossValue = $result->invoiceAudit?->auditoryFinalReport?->valor_glosa ?? 0;
                $acceptedValueEps = $result->accepted_value_eps ?? 0;
                $acceptedValueIps = $result->accepted_value_ips ?? 0;
                $ratifiedValue = $result->eps_ratified_value ?? 0;

                // Datos del invoice
                $invoiceData = [
                    "invoice_number" => $result->invoiceAudit?->invoice_number,
                    "sub_invoice_number" => $result->invoiceAudit?->invoice_number,
                    "gloss_code" => $result->invoiceAudit?->auditoryFinalReport?->codigos_glosa ?? "?????",
                    "contract_number" => $result->invoiceAudit?->contract_number,
                    "total_value" => formatNumber($totalValue),
                    "invoiced_month" => $result->invoiceAudit?->date_entry,
                    "affiliated_department" => $result->invoiceAudit?->third?->departmentAndCity?->departamento,
                    "initial_gloss_value" => formatNumber($initialGlossValue),
                    "pending_value" => "0",
                    "accepted_value_eps" => formatNumber($acceptedValueEps),
                    "accepted_value_ips" => formatNumber($acceptedValueIps),
                    "ratified_value" => formatNumber($ratifiedValue),
                    "justification" => "viene de la observacion de la tabla conciliation result",
                ];

                // Agregar a lista de invoices
                $pipe->rpush($this->invoicesKey, json_encode($invoiceData));

                // Actualizar totales atómicamente
                $pipe->hincrbyfloat($this->totalsKey, 'total_value', $totalValue);
                $pipe->hincrbyfloat($this->totalsKey, 'initial_gloss_value', $initialGlossValue);
                $pipe->hincrbyfloat($this->totalsKey, 'accepted_value_eps', $acceptedValueEps);
                $pipe->hincrbyfloat($this->totalsKey, 'accepted_value_ips', $acceptedValueIps);
                $pipe->hincrbyfloat($this->totalsKey, 'ratified_value', $ratifiedValue);
            }

            // Ejecutar todas las operaciones
            $pipe->execute();
        } catch (\Exception $e) {
            Log::error("Error en ProcessConciliationReportChunk - Process: {$this->processId}, User: {$this->userId}: " . $e->getMessage());
            throw $e;
        }
    }
}
