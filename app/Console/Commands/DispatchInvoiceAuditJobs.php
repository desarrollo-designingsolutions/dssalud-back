<?php

namespace App\Console\Commands;

use App\Jobs\ProcessInvoiceAuditCounts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

class DispatchInvoiceAuditJobs extends Command
{
    protected $signature = 'invoices:dispatch-audit-jobs';

    protected $description = 'Despacha jobs en lotes para procesar conteos de auditorías de facturas';

    public function handle()
    {
        $this->info('Iniciando procesamiento de conteos de auditorías...');

        $batchSize = 500; // Tamaño de lote fijo
        $processedChunks = 0;
        $batchIds = [];
        $queues = ['imports_1', 'imports_2', 'imports_3', 'imports_4', 'imports_5', 'imports_6', 'imports_7', 'imports_8', 'imports_9', 'imports_10']; // Lista de colas

        // Procesar invoice_audits en lotes de 500
        DB::table('invoice_audits')->orderBy('id')->chunk($batchSize, function ($facturas) use (&$processedChunks, &$batchIds, $queues) {
            $facturaIds = $facturas->pluck('id')->toArray();

            if (empty($facturaIds)) {
                $this->info('No se encontraron IDs en este lote.');

                return;
            }

            $this->info('Procesando lote con '.count($facturaIds).' IDs.');

            try {
                // Consulta agrupada para obtener conteos
                $dbCounts = DB::select(
                    'SELECT ia.id, COUNT(afr.id) AS db_count
                    FROM invoice_audits ia
                    INNER JOIN auditory_final_reports afr ON afr.factura_id = ia.id
                    WHERE ia.id IN ('.implode(',', array_fill(0, count($facturaIds), '?')).')
                    AND afr.valor_glosa > 0
                    GROUP BY ia.id',
                    $facturaIds
                );

                // Convertir a array [facturaId => dbCount]
                $counts = collect($dbCounts)->pluck('db_count', 'id')->toArray();

                // Seleccionar la cola de forma cíclica
                $queue = $queues[$processedChunks % count($queues)];

                // Despachar job con los conteos y la cola seleccionada
                $batch = Bus::batch([
                    new ProcessInvoiceAuditCounts($counts, $queue),
                ])->then(function () {
                    // $this->info("Lote {$processedChunks} completado (tamaño: {$batchSize}, cola: {$queue}).");
                })->catch(function ($batch, $e) {
                    // $this->error("Lote {$processedChunks} (cola: {$queue}) falló: {$e->getMessage()}");
                })->dispatch();

                $batchIds[] = $batch->id;
                // $this->info("Lote {$processedChunks} despachado en cola {$queue}. Batch ID: {$batch->id}");
            } catch (\Exception $e) {
                \Log::error("Error procesando lote {$processedChunks}: {$e->getMessage()}");
                // $this->error("Error en lote {$processedChunks}: {$e->getMessage()}");
            }

            $processedChunks++;
        });

        $this->info('Todos los lotes han sido despachados. Batch IDs: '.implode(', ', $batchIds));
    }
}
