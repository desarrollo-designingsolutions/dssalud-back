<?php

namespace App\Jobs\Filing;

use App\Enums\Filing\StatusFilingEnum;
use App\Events\FilingFinishProcessJob;
use App\Events\FilingProgressEvent;
use App\Models\Filing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessFilingValidationTxt implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $build;

    public $filingId;

    public $lastProcess;

    /**
     * Create a new job instance.
     */
    public function __construct($filingId, $build, $lastProcess = true)
    {
        $this->build = $build;
        $this->filingId = $filingId;
        $this->lastProcess = $lastProcess;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Obtener los datos JSON de la base de datos
        $filing = Filing::select('id', 'validationTxt', 'status')->find($this->filingId);
        $contenido_json = json_decode($filing->validationTxt, true); // Decodificar los datos JSON en un array asociativo

        // Ejecutar la función validateDataFilesTxt para obtener los nuevos datos
        $infoValidation = validateDataFilesTxt([$this->build]);

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
        $filing->validationTxt = json_encode($contenido_json2);
        $filing->save();

        if (! empty($this->lastProcess)) {

            // Ya sea que tenga errores txt o no, la radicacion queda abierta
            $filing->status = StatusFilingEnum::FILING_EST_008;
            $filing->save();

            FilingFinishProcessJob::dispatch($filing->id);

            // // Eliminamos el archivo zip subido
            // deletefileZipData($filing);

            FilingProgressEvent::dispatch($filing->id, 100);
        }
    }
}
