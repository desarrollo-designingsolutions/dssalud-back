<?php

namespace App\Jobs\Glosa;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessGlosasServiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $service_id;


    public function __construct($service_id)
    {
        $this->service_id = $service_id;
    }

    public function handle(): void
    {
        // sleep(3);

        changeServiceData($this->service_id);

    }
}
