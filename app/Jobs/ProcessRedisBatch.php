<?php

namespace App\Jobs;

use App\Models\Company;
use App\Services\CacheService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class ProcessRedisBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $companyId;

    protected $modelClass;

    protected $elements;

    public function __construct($companyId, $modelClass, $elements)
    {
        $this->companyId = $companyId;
        $this->modelClass = $modelClass;
        $this->elements = $elements;
    }

    public function handle(CacheService $cacheService): void
    {
        try {
            $table = (new $this->modelClass)->getTable();
            $company = Company::find($this->companyId);

            foreach ($this->elements as $element) {
                $serviceData = $element->toArray();
                $request = [
                    'company_id' => $this->companyId,
                    'element_id' => $element->id,
                ];

                // Generar la clave de caché
                $cacheKey = $cacheService->generateKey("{$table}:company_{$this->companyId}:cronjob", $request, 'hash');
                Redis::hmset($cacheKey, $serviceData);

                $cacheKey2 = $cacheService->generateKey("{$table}:company_{$this->companyId}:ids_set_cronjob", $request, 'set');
                Redis::sadd($cacheKey2, $element->id);
            }
        } catch (\Throwable $e) {
            \Log::error("Error in ProcessRedisBatch for {$this->modelClass}, company {$this->companyId}: ".$e->getMessage(), [
                'company_id' => $this->companyId,
                'model' => $this->modelClass,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
