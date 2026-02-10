<?php

namespace App\Jobs\Glosa;

use App\Events\ProgressCircular;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessGlosasServiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected $serviceId, protected $userId, protected $progress) {}

    public function handle(): void
    {
        changeServiceData($this->serviceId);

        // Emitir el evento de progreso
        ProgressCircular::dispatch("glosa_service_jobs.{$this->userId}", $this->progress);

    }
}
