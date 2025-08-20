<?php

namespace App\Jobs\Conciliation;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use App\Helpers\Constants;
use Illuminate\Support\Facades\Log;
use App\Models\AuditoryFinalReport;

class ProcessConciliationReportChunk implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $invoicesIds;
    protected $offset;
    protected $limit;
    protected $tempFileName;

    public function __construct($invoicesIds, $offset, $limit, $tempFileName)
    {
        $this->invoicesIds = $invoicesIds;
        $this->offset = $offset;
        $this->limit = $limit;
        $this->tempFileName = $tempFileName;
    }

    public function handle()
    {
        if ($this->batch()->cancelled()) {
            return;
        }

        // Obtener los IDs del chunk actual
        $chunkIds = array_slice($this->invoicesIds, $this->offset, $this->limit);

        // Obtener las facturas del chunk
        $invoices = AuditoryFinalReport::whereIn("factura_id", $chunkIds)
            ->with([
                'invoiceAudit',
                'conciliationResult',
                'invoiceAudit.third.departmentAndCity'
            ])
            ->get();

        // Variables para calcular totales
        $totals = [
            'total_value' => 0,
            'initial_gloss_value' => 0,
            'accepted_value_eps' => 0,
            'accepted_value_ips' => 0,
            'ratified_value' => 0
        ];

        // Procesar facturas para el reporte
        $processedInvoices = $invoices->map(function ($value) use (&$totals) {
            $totalValue = $value->invoiceAudit?->total_value ?? 0;
            $initialGlossValue = $value->valor_glosa ?? 0;
            $acceptedValueEps = $value->conciliationResult?->accepted_value_eps ?? 0;
            $acceptedValueIps = $value->conciliationResult?->accepted_value_ips ?? 0;
            $ratifiedValue = $value->conciliationResult?->eps_ratified_value ?? 0;

            // Acumular totales
            $totals['total_value'] += $totalValue;
            $totals['initial_gloss_value'] += $initialGlossValue;
            $totals['accepted_value_eps'] += $acceptedValueEps;
            $totals['accepted_value_ips'] += $acceptedValueIps;
            $totals['ratified_value'] += $ratifiedValue;

            return [
                "invoice_number" => $value->invoiceAudit?->invoice_number,
                "sub_invoice_number" => $value->invoiceAudit?->invoice_number,
                "gloss_code" => $value->codigos_glosa,
                "contract_number" => $value->contrato,
                "total_value" => formatNumber($totalValue),
                "invoiced_month" => $value->invoiceAudit?->date_entry,
                "affiliated_department" => $value->invoiceAudit?->third?->departmentAndCity?->departamento,
                "initial_gloss_value" => formatNumber($initialGlossValue),
                "pending_value" => "0",
                "accepted_value_eps" => formatNumber($acceptedValueEps),
                "accepted_value_ips" => formatNumber($acceptedValueIps),
                "ratified_value" => formatNumber($ratifiedValue),
                "justification" => "viene de la observacion de la tabla conciliation result",
            ];
        });

        // Guardar chunk en archivo temporal (JSON)
        $filePath = 'temp/conciliation_reports/' . $this->tempFileName;

        // Leer datos existentes
        if (Storage::disk(Constants::DISK_FILES)->exists($filePath)) {
            $existingData = json_decode(Storage::disk(Constants::DISK_FILES)->get($filePath), true);
        } else {
            $existingData = ['invoices' => [], 'totals' => []];
        }

        // Combinar datos
        $existingData['invoices'] = array_merge($existingData['invoices'], $processedInvoices->toArray());

        // Combinar totales
        foreach ($totals as $key => $value) {
            $existingData['totals'][$key] = ($existingData['totals'][$key] ?? 0) + $value;
        }

        Storage::disk(Constants::DISK_FILES)->put($filePath, json_encode($existingData));
    }
}
