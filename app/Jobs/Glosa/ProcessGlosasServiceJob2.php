<?php

namespace App\Jobs\Glosa;

use App\Models\Company;
use App\Models\Service;
use App\Services\CacheService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class ProcessGlosasServiceJob2 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(CacheService $cacheService): void
    {
        $model = Service::class;
        $table = (new $model)->getTable();


        $companies_ids = Company::select(["id"])->get();
        $lastRunKey = 'last_glosas_job_run';
        $lastRun = Redis::get($lastRunKey) ? Carbon::parse(Redis::get($lastRunKey)) : Carbon::today();

        foreach ($companies_ids as $company) {
            $results = [];
            $model::where('company_id', $company->id)
                ->where('created_at', '>=', $lastRun)
                ->chunk(100, function ($elements) use (&$results, $table, $company, $cacheService) {
                    foreach ($elements as $element) {
                        $serviceData = $element->toArray();

                        $request = [
                            'company_id' => $company->id,
                            'element_id' => $element->id,
                        ];

                        // Generar la clave de caché
                        $cacheKey = $cacheService->generateKey("{$table}_cronjob", $request, 'hash');


                        Redis::hmset($cacheKey, $serviceData);
                        $results[$element->id] = $serviceData;
                    }
                });
        }

        // Actualizar la fecha de última ejecución
        Redis::set($lastRunKey, Carbon::now());
    }
}
