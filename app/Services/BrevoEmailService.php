<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class BrevoEmailService
{
    protected $client;

    protected $url;

    protected $apiKey;

    protected $templateId = '';

    protected $subject = '';

    protected $senderName = '';

    protected $senderEmail = '';

    protected $to = [];

    protected $cc = [];

    protected $cco = [];

    protected $params = [];

    private $attachments = [];

    public function __construct()
    {
        $this->client = new Client;
        $this->url = 'https://api.brevo.com/v3/smtp/email';
        $this->apiKey = env('BREVO_API_KEY');
        $this->senderEmail = env('MAIL_FROM_ADDRESS_BREVO');
        $this->senderName = env('MAIL_FROM_NAME_BREVO');
    }

    public function setTo(array $to)
    {
        $this->to = $to;
    }

    public function setCc(array $cc)
    {
        $this->cc = $cc;
    }

    public function setCco(array $cco)
    {
        $this->cco = $cco;
    }

    public function setTemplateId($templateId)
    {
        $this->templateId = $templateId;
    }

    public function setSenderName(string $senderName)
    {
        $this->senderName = $senderName;
    }

    public function setSenderEmail(string $senderEmail)
    {
        $this->senderEmail = $senderEmail;
    }

    public function setSubject(string $subject)
    {
        $this->subject = $subject;
    }

    public function setParams(array $params)
    {
        $this->params = $params;
    }

    public function setAttachments($attachments)
    {
        $this->attachments = $attachments;
    }

    public function sendEmail()
    {
        $sender = [
            'name' => $this->senderName,
            'email' => $this->senderEmail,
        ];

        // Datos para el correo
        $data = [
            'sender' => $sender,
            'to' => $this->to,
            'subject' => $this->subject,
            'templateId' => $this->templateId,
        ];

        if ($this->cc && is_array($this->cc)) {
            $data['cc'] = $this->cc;
        }
        if ($this->cco && is_array($this->cco)) {
            $data['bcc'] = $this->cco;
        }
        if ($this->params && is_array($this->params)) {
            $data['params'] = $this->params;
        }
        if ($this->attachments && is_array($this->attachments)) {
            $data['attachment'] = $this->attachments;
        }

        try {
            $response = $this->client->post($this->url, [
                'headers' => [
                    'accept' => 'application/json',
                    'api-key' => $this->apiKey,
                    'content-type' => 'application/json',
                ],
                'json' => $data,
            ]);

            return [
                'body' => json_decode($response->getBody(), true),
                'status_code' => $response->getStatusCode(),
            ];
        } catch (RequestException $e) {

            // Registrar el error en el log
            Log::error('Error al enviar el correo a '.json_encode($this->to), [
                'error_message' => $e->getMessage(),
                'request_data' => $data,
            ]);

            return [
                'error' => $e->getMessage(),
                'code' => $e->getCode() ?: 500,
            ];
        }
    }
}
