<?php

namespace App\Jobs;

use App\Services\BrevoEmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BrevoProcessSendEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $templateId;

    public $subject;

    public $params;

    public $attachments;

    public $emailTo;

    public $cc;

    public $cco;

    /**
     * Create a new job instance.
     */
    public function __construct($emailTo, $subject, $templateId, $params = null, $attachments = [], $cc = null, $cco = null)
    {
        $emails = [];
        if (is_array($emailTo)) {
            $emails = array_merge($emailTo);
        } elseif (is_string($emailTo)) {
            $emails[] = $emailTo;
        }

        $this->templateId = $templateId;
        $this->subject = $subject;
        $this->params = $params;
        $this->attachments = $attachments;
        $this->emailTo = $emails;
        $this->cc = $cc;
        $this->cco = $cco;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $brevoEmailService = new BrevoEmailService;
        $brevoEmailService->setTemplateId($this->templateId);
        $brevoEmailService->setSubject($this->subject);
        $brevoEmailService->setTo($this->emailTo);

        if ($this->params && is_array($this->params)) {
            $brevoEmailService->setParams($this->params);
        }
        if ($this->cc && is_array($this->cc)) {
            $brevoEmailService->setCc($this->cc);
        }
        if ($this->cco && is_array($this->cco)) {
            $brevoEmailService->setCco($this->cco);
        }

        $brevoEmailService->sendEmail();
    }
}
