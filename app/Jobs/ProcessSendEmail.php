<?php

namespace App\Jobs;

use App\Services\MailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessSendEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $emailTo;

    public $view;

    public $subject;

    public $data;

    public $attachments;

    public $remitter;

    /**
     * Create a new job instance.
     */
    public function __construct($emailTo, $view, $subject, $data, $attachments = [], $remitter = null)
    {
        $this->emailTo = $emailTo;
        $this->view = $view;
        $this->subject = $subject;
        $this->data = $data;
        $this->attachments = $attachments;
        $this->remitter = $remitter;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $emails = [];
        if (is_array($this->emailTo)) {
            $emails = array_merge($this->emailTo);
        } elseif (is_string($this->emailTo)) {
            $emails[] = $this->emailTo;
        }

        $mailService = new MailService;
        $mailService->setEmailTo($emails);
        $mailService->setView($this->view);
        $mailService->setSubject($this->subject);
        // $mailService->sendMessage($this->data);
    }
}
