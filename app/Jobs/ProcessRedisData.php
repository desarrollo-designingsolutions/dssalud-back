<?php

namespace App\Jobs;

use App\Models\Assignment;
use App\Models\InvoiceAudit;
use App\Services\CacheService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessRedisData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(CacheService $cacheService): void
    {
        try {
            $models = [InvoiceAudit::class];
            // $models = [InvoiceAudit::class, Assignment::class];

            foreach ($models as $model) {
                logger('Processing model: '.$model);
                ProcessRedisModel::dispatch($model)->onQueue('models');
            }
        } catch (\Throwable $e) {
            \Log::error('Error in ProcessRedisData: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
