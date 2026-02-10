<?php

namespace App\Imports\ConciliationImport\Console;

use App\Models\AuditoryFinalReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class PreloadDbFacturaGlosaCountsCache extends Command
{
    protected $signature = 'cache:preload-db-factura-glosa-counts {--force : Force a refresh of the cache even if it exists}';

    protected $description = 'Preloads counts of factura_id (valor_glosa > 0) into a master Redis cache.';

    public function handle(): void
    {
        $redisMasterKey = 'db_factura_glosa_counts_master';
        $forceRefresh = $this->option('force');

        if (Redis::connection('redis_6380')->exists($redisMasterKey) && ! $forceRefresh) {
            $this->info('DB factura glosa counts master cache already exists. Use --force to refresh.');
            Log::info('DB factura glosa counts master cache already exists. Skipping preload.');

            return;
        }

        $this->info('Preloading DB factura glosa counts data into master Redis cache...');
        Log::info('Starting preload of DB factura glosa counts data into master Redis cache.');

        Redis::connection('redis_6380')->del($redisMasterKey); // Clear existing cache if forcing or not exists

        $count = 0;
        $startTime = microtime(true);
        $pipeline = Redis::connection('redis_6380')->pipeline();
        $chunkSize = 1000; // Adjust chunk size based on your memory/performance needs

        AuditoryFinalReport::select('factura_id', DB::raw('COUNT(*) as total_count'))
            ->where('valor_glosa', '>', 0)
            ->groupBy('factura_id')
            ->orderBy('factura_id')
            ->chunk($chunkSize, function ($results) use (&$count, $pipeline, $redisMasterKey) {
                foreach ($results as $result) {
                    $pipeline->hset($redisMasterKey, $result->factura_id, (string) $result->total_count);
                    $count++;
                }
            });

        $pipeline->execute(); // Execute the pipeline after all chunks

        $endTime = microtime(true);
        $this->info(sprintf('Preload completed: %d unique factura_id counts in %.2f seconds.', $count, ($endTime - $startTime)));
        Log::info(sprintf('Preload of db_factura_glosa_counts_master completed: %d unique factura_id counts in %.2f seconds.', $count, ($endTime - $startTime)));

        // Set an expiration for the master cache, e.g., 6 months (approx 180 days)
        Redis::connection('redis_6380')->expire($redisMasterKey, 60 * 60 * 24 * 180);
        Log::info('DB factura glosa counts master cache set to expire in 180 days.');
    }
}
