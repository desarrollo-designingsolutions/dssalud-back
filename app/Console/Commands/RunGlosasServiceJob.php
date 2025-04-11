<?php

namespace App\Console\Commands;

use App\Jobs\Glosa\ProcessGlosasServiceJob2;
use Illuminate\Console\Command;

class RunGlosasServiceJob extends Command
{
    protected $signature = 'glosas:run-service-job';
    protected $description = 'Dispatch ProcessGlosasServiceJob2';

    public function handle()
    {
        ProcessGlosasServiceJob2::dispatch();
        $this->info('Job dispatched successfully.');
    }
}
