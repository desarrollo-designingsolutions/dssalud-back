<?php

namespace App\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class ProcessInvoiceAuditCounts implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hora de timeout por job

    protected $counts;

    /**
     * Create a new job instance.
     *
     * @param  array  $counts  Array de [facturaId => dbCount]
     * @param  string  $queue  Nombre de la cola (imports_1, imports_2, etc.)
     */
    public function __construct(array $counts, string $queue = 'imports_2')
    {
        $this->counts = $counts;
        $this->onQueue($queue); // Establecer la cola dinámicamente
    }

    public function handle()
    {
        foreach ($this->counts as $facturaId => $dbCount) {
            $redisKey = "invoice_audit:{$facturaId}:db_count";

            // Saltar si ya existe en Redis
            if (Redis::connection('redis_6380')->exists($redisKey)) {
                continue;
            }

            try {
                // Guardar en Redis solo si dbCount > 0, sin expiración
                if ($dbCount > 0) {
                    Redis::connection('redis_6380')->set($redisKey, $dbCount);
                }
            } catch (\Exception $e) {
                // Registrar error y continuar
                \Log::error("Error procesando invoice_audit ID {$facturaId}: {$e->getMessage()}");

                continue;
            }
        }
    }
}
