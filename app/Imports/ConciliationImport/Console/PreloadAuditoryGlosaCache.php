<?php

namespace App\Imports\ConciliationImport\Console;

use App\Models\AuditoryFinalReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class PreloadAuditoryGlosaCache extends Command
{
    protected $signature = 'cache:preload-auditory-glosa {--force : Force a refresh of the cache even if it exists}';

    protected $description = 'Preloads all auditory_final_reports IDs and valor_glosa into a master Redis cache.';

    public function handle(): void
    {
        $redisMasterKey = 'auditory_glosa_master';
        $forceRefresh = $this->option('force');

        if (Redis::connection('redis_6380')->exists($redisMasterKey) && ! $forceRefresh) {
            $this->info('Auditory glosa master cache already exists. Use --force to refresh.');
            Log::info('Auditory glosa master cache already exists. Skipping preload.');

            return;
        }

        $this->info('Preloading auditory_final_reports data into master Redis cache...');
        Log::info('Starting preload of auditory_final_reports data into master Redis cache.');

        Redis::connection('redis_6380')->del($redisMasterKey); // Clear existing cache if forcing or not exists

        $count = 0;
        $startTime = microtime(true);
        $pipeline = Redis::connection('redis_6380')->pipeline();
        $chunkSize = 5000; // Adjust chunk size based on your memory/performance needs

        AuditoryFinalReport::select('id', 'valor_glosa')
            ->orderBy('id')
            ->chunk($chunkSize, function ($reports) use (&$count, $pipeline, $redisMasterKey) {
                foreach ($reports as $report) {
                    $pipeline->hset($redisMasterKey, $report->id, (string) $report->valor_glosa);
                    $count++;
                }
            });

        $pipeline->execute(); // Execute the pipeline after all chunks

        $endTime = microtime(true);
        $this->info(sprintf('Preload completed: %d records in %.2f seconds.', $count, ($endTime - $startTime)));
        Log::info(sprintf('Preload of auditory_glosa_master completed: %d records in %.2f seconds.', $count, ($endTime - $startTime)));

        // Set an expiration for the master cache, e.g., 6 months (approx 180 days)
        Redis::connection('redis_6380')->expire($redisMasterKey, 60 * 60 * 24 * 180);
        Log::info('Auditory glosa master cache set to expire in 180 days.');
    }
}
