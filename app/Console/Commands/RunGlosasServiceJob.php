<?php

namespace App\Console\Commands;

use App\Jobs\ProcessRedisData;
use Illuminate\Console\Command;

class RunGlosasServiceJob extends Command
{
    protected $signature = 'redis:run-service-job';

    protected $description = 'Dispatch ProcessGlosasServiceJob2';

    public function handle()
    {
        ProcessRedisData::dispatch();
        $this->info('Job dispatched successfully.');
    }
}
