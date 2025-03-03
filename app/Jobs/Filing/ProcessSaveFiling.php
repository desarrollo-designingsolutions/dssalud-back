<?php

namespace App\Jobs\Filing;

use App\Enums\Filing\StatusFilingEnum;
use App\Events\FilingFinishProcessJob;
use App\Models\Filing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessSaveFiling implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $filingId;

    public $userData;

    /**
     * Create a new job instance.
     */
    public function __construct($filingId, $userData = null)
    {
        $this->filingId = $filingId;
        $this->userData = $userData;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //busco el registro
        $filing = Filing::find($this->filingId);

        //cambio el estado a "PROCESSED"
        $filing->status = StatusFilingEnum::FILING_EST_002;
        $filing->save();

        FilingFinishProcessJob::dispatch($filing->id);




    }
}
