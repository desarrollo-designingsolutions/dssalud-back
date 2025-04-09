<?php

namespace App\Jobs\Glosa;

use App\Models\Service;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class ProcessGlosasServiceJob2 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct() {}

    public function handle(): void
    {
        Service::chunk(100, function ($elements) {
            foreach ($elements as $element) {
                $serviceData = $element->toArray();
                $key = "services:{$element->id}";
                Redis::hmset($key, $serviceData);
                Redis::sadd('services:ids_set', $element->id);
            }
        });

        // PARA CONTINUAR
        // que solo siga agregando los nuevos registros

    }
}
