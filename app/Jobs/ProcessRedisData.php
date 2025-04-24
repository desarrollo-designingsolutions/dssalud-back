<?php

namespace App\Jobs;

use App\Models\Assignment;
use App\Models\Company;
use App\Models\InvoiceAudit;
use App\Models\Service;
use App\Models\User;
use App\Services\CacheService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class ProcessRedisData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(CacheService $cacheService): void
    {
        $models = [InvoiceAudit::class, Assignment::class];
        foreach ($models as $key => $model) {
            $table = (new $model)->getTable();
    
    
            $companies_ids = Company::select(["id"])->get();

            $lastRunKey = $cacheService->generateKey("{$table}:last_date_job_run", [], 'string');

            $lastRun = Redis::get($lastRunKey) ? Carbon::parse(Redis::get($lastRunKey)) : null;
    
            foreach ($companies_ids as $company) {
                $results = [];
                $model::where('company_id', $company->id)
                    ->where(function ($query) use ($lastRun) {
    
                        if (!empty($lastRun)) {
                            $query->where('created_at', '>=', $lastRun);
                        }
                    })
                    ->chunk(100, function ($elements) use (&$results, $table, $company, $cacheService) {
                        foreach ($elements as $element) {
                            $serviceData = $element->toArray();
                            $request = [
                                'company_id' => $company->id,
                                'element_id' => $element->id,
                            ];
    
                            // Generar la clave de caché
                            $cacheKey = $cacheService->generateKey("{$table}:company_{$company->id}:cronjob", $request, 'hash');
                            Redis::hmset($cacheKey, $serviceData);
                            
                            $cacheKey2 = $cacheService->generateKey("{$table}:company_{$company->id}:ids_set_cronjob", $request, 'set');
                            Redis::sadd($cacheKey2, $element->id);
    
                            $results[$element->id] = $serviceData;
                        }
                    });
            }
    
            // Actualizar la fecha de última ejecución
            Redis::set($lastRunKey, Carbon::now());
        }
    }
}
