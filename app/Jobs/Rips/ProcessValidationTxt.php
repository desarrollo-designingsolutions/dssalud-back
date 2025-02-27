<?php

namespace App\Jobs\Rips;

use App\Enums\StatusRipsEnum;
use App\Jobs\ProcessSendEmail;
use App\Models\Rip;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessValidationTxt implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $build;

    public $ripId;

    public $userData;

    public $lastProcess;

    /**
     * Create a new job instance.
     */
    public function __construct($ripId, $build, $userData = null, $lastProcess = true)
    {
        $this->build = $build;
        $this->ripId = $ripId;
        $this->userData = $userData;
        $this->lastProcess = $lastProcess;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Obtener los datos JSON de la base de datos
        $rip = Rip::select('id', 'validationTxt', 'status')->find($this->ripId);
        $contenido_json = json_decode($rip->validationTxt, true); // Decodificar los datos JSON en un array asociativo

        // Ejecutar la función validateDataFilesTxt para obtener los nuevos datos
        $infoValidation = validateDataFilesTxt($this->build);

        // Combinar los datos
        $contenido_json2 = [];

        // Iterar sobre cada elemento del array
        foreach ($infoValidation as $key => &$elemento) {
            // Realizar las operaciones de suma y concatenación según el tipo de dato
            if (is_array($elemento)) {
                $arr = [];
                if (isset($contenido_json[$key])) {
                    $arr = $contenido_json[$key];
                }
                // Concatenar arrays
                $valor = [...$elemento, ...$arr];
            } elseif (is_numeric($elemento)) {
                $sum = 0;
                if (isset($contenido_json[$key])) {
                    $sum = $contenido_json[$key];
                }
                // Sumar valores numéricos
                $valor = $elemento + $sum;
            }

            // Agregar el elemento procesado al contenido JSON
            $contenido_json2[$key] = $valor;
        }

        $order = [
            'type' => 'desc',
            'field' => 'validacion_type_Y',
        ];

        // Ordenar el array usando usort y la función de comparación del helper
        usort($contenido_json2['errorMessages'], function ($a, $b) use ($order) {
            return customSort($a, $b, [$order]);
        });

        // Actualizar la información de la validación excel en el registro
        $rip->validationTxt = json_encode($contenido_json2);
        $rip->save();

        if (! empty($this->lastProcess)) {
            if (count($contenido_json2['errorMessages']) > 0) {
                // Si tiene errores de validación, cambio el estado
                $rip->status = StatusRipsEnum::PROCESSED;
                $rip->save();
            } else {

                // Si no tiene errores de validación
                ProcessSaveRips::dispatch($rip->id, $this->userData);
            }

            // Eliminamos el archivo zip subido
            deletefileZipData($rip);

            if ($this->userData) {
                ProcessSendEmail::dispatch($this->userData->email, 'Mails.Rips.ValidationTxt', 'Información Validaciones TXT', [
                    'infoValidation' => $infoValidation,
                ]);
            }
        }
    }
}
