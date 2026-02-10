<?php

namespace App\Jobs;

use App\Models\Company;
use App\Services\CacheService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class ProcessRedisModel implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $modelClass;

    public function __construct($modelClass)
    {
        $this->modelClass = $modelClass;
    }

    public function handle(CacheService $cacheService): void
    {
        try {
            $table = (new $this->modelClass)->getTable();
            $lastRunKey = $cacheService->generateKey("{$table}:last_date_job_run", [], 'string');
            $lastRun = Redis::get($lastRunKey) ? Carbon::parse(Redis::get($lastRunKey)) : null;

            Company::select('id')->cursor()->each(function ($company) use ($lastRun) {
                $query = $this->modelClass::where('company_id', $company->id);
                if ($lastRun) {
                    $query->where('created_at', '>=', $lastRun);
                }
                $query->chunkById(50, function ($elements) use ($company) {
                    ProcessRedisBatch::dispatch($company->id, $this->modelClass, $elements)->onQueue('batches');
                });
                gc_collect_cycles();
            });

            Redis::set($lastRunKey, Carbon::now());
        } catch (\Throwable $e) {
            \Log::error("Error in ProcessRedisModel for {$this->modelClass}: ".$e->getMessage(), [
                'model' => $this->modelClass,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
