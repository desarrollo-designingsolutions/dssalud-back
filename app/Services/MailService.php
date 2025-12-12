<?php

namespace App\Services;

use App\Mail\MailReceived;
use Illuminate\Support\Facades\Mail;

class MailService
{
    private $email = [];

    private $cc = [];

    private $cco = [];

    private $view = '';

    private $subject = '';

    private $fromAddress = null;

    private $fromName = null;

    private $files = [];

    public function setSmtpData($email, $password)
    {
        Config(['mail.mailers.smtp.username' => $email]);
        Config(['mail.mailers.smtp.password' => $password]);
    }

    public function setEmailTo($email)
    {
        $this->email = $email;
    }

    public function setCc($cc)
    {
        $this->cc = $cc;
    }

    public function setCco($cco)
    {
        $this->cco = $cco;
    }

    public function setView($view)
    {
        $this->view = $view;
    }

    public function setSubject($subject)
    {
        $this->subject = $subject;
    }

    public function setFromAddress($fromAddress)
    {
        $this->fromAddress = $fromAddress;
    }

    public function setFromName($fromName)
    {
        $this->fromName = $fromName;
    }

    public function setFile($files)
    {
        $this->files = $files;
    }

    public function sendMessage($data = [])
    {
        // $this->cco[] = env('MAILREPO');
        $this->fromAddress = $this->fromAddress ?? env('MAIL_FROM_ADDRESS');
        $this->fromName = $this->fromName ?? env('MAIL_FROM_NAME');

        Mail::to($this->email)->cc($this->cc)->bcc($this->cco)->send(new MailReceived($this->view, $this->subject, $this->fromAddress, $this->fromName, $data, $this->files));
    }
}
