<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WhatsappService
{
    private $token = 'EAAQolaZB6nkoBAHYZCThRM9PPnZA0IlC9JcwGMQYzNuHcPlBKLiaPE4oRY0mxCadZCKv6QckxcdZC1d9pkutJScMpvtJPC4ZC5eVrFyc3rNLaGYb8bQpBq7MgAulNkb4A7mTUcy4Yi9yR7giDYb6EtRbsxOsJBbZCVyBuih3lxfGlMAOsHeL0DWPvG7cWHuYTOp5Tt0L869tgZDZD';

    private $phoneId = '115629954783824';

    private $version = 'v15.0';

    private $url = 'https://graph.facebook.com/';

    private $number;

    private $template;

    public function sendMessage($request, $type)
    {
        try {
            $this->dataMessage($request, $type);
            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $this->number,
                'type' => 'template',
                'template' => $this->template,
            ];
            $message = Http::withToken($this->token)->post($this->url.$this->version.'/'.$this->phoneId.'/messages', $payload)->throw()->json();

            return response()->json(['data' => $message], 200);
        } catch (\Throwable $th) {
            return response()->json(['data' => $th->getMessage(), 'linea', $th->getLine()], 500);
        }

    }

    public function dataMessage($request, $type)
    {
        if ($type == 'vetszoo_produccion') {
            $this->number = $request['number'];
            $this->template = [
                'name' => 'vetszoo_produccion',
                'language' => ['code' => 'es'],
                'components' => [
                    ['type' => 'body',
                        'parameters' => [
                            ['type' => 'text',
                                'text' => $request['name'], ], // Nombre del propietario
                        ], ],
                ],
            ];
        }
        if ($type == 'vetszoo_notificaciones') {
            $this->number = $request['number'];
            $this->template = [
                'name' => 'vetszoo_notificaciones',
                'language' => ['code' => 'es'],
                'components' => [
                    ['type' => 'body',
                        'parameters' => [
                            ['type' => 'text',
                                'text' => $request['messaje'], ], // Nombre del aspirante
                        ], ],
                ],
            ];
        }

    }
}
