<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MailReceived extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    public $view;

    public $subject;

    public $files;

    public $fromAddress;

    public $fromName;

    /**
     * Create a new message instance.
     */
    public function __construct($view, $subject, $fromAddress, $fromName, $data, $files)
    {
        $this->view = $view;
        $this->subject = $subject;
        $this->data = $data;
        $this->files = $files;
        $this->fromAddress = $fromAddress;
        $this->fromName = $fromName;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $message = $this
            ->from($this->fromAddress, $this->fromName)
            ->view($this->view)
            ->subject($this->subject)
            ->with('data', $this->data);

        foreach ($this->files as $archivo) {

            if ($archivo['type'] == 'binary') {
                $message->attach($archivo->getRealPath(), [
                    'as' => $archivo['name'] ?? $archivo['file']->getClientOriginalName().'.'.$archivo['file']->getClientOriginalExtension(),
                    'mime' => $archivo['mime'] ?? $archivo['file']->getClientMimeType(),
                ]);
            } elseif ($archivo['type'] == 'base64') {
                $filename = str_replace("\0", '', $archivo['name']); // Eliminar caracteres nulos del nombre del archivo
                $message->attachData(base64_decode($archivo['file']), $filename, [
                    'mime' => $archivo['mime'],
                ]);
            } else {
                $f = str_replace(request()->root(), '', $archivo['file']);
                $message->attach(public_path($f), [
                    'as' => $archivo['name'] ?? $archivo['file']->getClientOriginalName().'.'.$archivo['file']->getClientOriginalExtension(),
                ]);
            }
        }

        return $message;
    }
}
