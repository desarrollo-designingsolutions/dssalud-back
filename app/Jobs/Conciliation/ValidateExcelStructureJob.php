<?php

namespace App\Jobs\Conciliation;

use App\Services\Excel\ExcelValidator;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels; // Asegúrate de importar Redis
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class ValidateExcelStructureJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600;

    private array $expectedHeaders = [
        'ID', 'FACTURA_ID', 'SERVICIO_ID', 'ORIGIN', 'NIT', 'RAZON_SOCIAL',
        'NUMERO_FACTURA', 'FECHA_INICIO', 'FECHA_FIN', 'MODALIDAD', 'REGIMEN',
        'COBERTURA', 'CONTRATO', 'TIPO_DOCUMENTO', 'NUMERO_DOCUMENTO',
        'PRIMER_NOMBRE', 'SEGUNDO_NOMBRE', 'PRIMER_APELLIDO', 'SEGUNDO_APELLIDO',
        'GENERO', 'CODIGO_SERVICIO', 'DESCRIPCION_SERVICIO', 'CANTIDAD_SERVICIO',
        'VALOR_UNITARIO_SERVICIO', 'VALOR_TOTAL_SERVICIO', 'CODIGOS_GLOSA',
        'OBSERVACIONES_GLOSAS', 'VALOR_GLOSA', 'VALOR_APROBADO', 'ESTADO_RESPUESTA',
        'NUMERO_DE_AUTORIZACION', 'VALOR_ACEPTADO_IPS', 'VALOR_ACEPTADO_EPS',
        'VALOR_RATIFICADO_EPS', 'OBSERVACIONES',
    ];

    public function __construct(
        private string $filePath,
        private array $validationConfig = []
    ) {}

    public function handle()
    {
        $batchId = $this->batch()->id; // Obtener el batchId

        try {
            $defaultConfig = [
                'validate_headers' => true,
                'validate_data' => false,
                'max_rows' => null,
                'stop_on_first_error' => false,
                'sample_size' => 1000,
            ];
            $config = array_merge($defaultConfig, $this->validationConfig);

            $validationRules = [
                'ID' => ['required' => true],
                'FACTURA_ID' => ['required' => true],
                'SERVICIO_ID' => ['required' => true],
                'ESTADO_RESPUESTA' => ['required' => true],
                'VALOR_ACEPTADO_IPS' => ['required' => true],
                'VALOR_ACEPTADO_EPS' => ['required' => true],
                'VALOR_RATIFICADO_EPS' => ['required' => true],
                'OBSERVACIONES' => ['required' => true],

            ];

            if ($config['validate_headers'] && ! $config['validate_data']) {
                $config['max_rows'] = 1;
                $this->timeout = 300;
            }

            // Pasar el batchId al ExcelValidator para que pueda guardar errores directamente en Redis
            $config['batch_id'] = $batchId;
            $result = ExcelValidator::validateFile(
                $this->filePath,
                $this->expectedHeaders,
                $validationRules,
                $config
            );

            if (! $result['valid']) {
                Log::error('Errores de validación de estructura encontrados', ['errors' => $result['errors']]);
                // Los errores ya deberían estar en Redis si ExcelValidator los manejó.
                // Si no, podrías agregarlos aquí manualmente:
                // foreach ($result['errors'] as $error) {
                //     Redis::rpush("batch:{$batchId}:errors", json_encode($error));
                // }
                throw new \Exception('Validación de estructura fallida: '.count($result['errors']).' errores encontrados.');
            }

        } catch (Throwable $e) {
            Log::error('Error encontrado en ValidateExcelStructureJob durante validación de estructura: '.$e->getMessage());
            $this->fail($e);
        }
    }

    public function failed(Throwable $exception)
    {
        Log::error('Job de validación de estructura fallido: '.$exception->getMessage());
        // Si el job falla por una excepción no capturada por ExcelValidator,
        // y no se guardaron errores en Redis, podrías añadir un error genérico aquí.
        // Por ejemplo:
        Redis::rpush("batch:{$this->batch()->id}:errors", json_encode([
            'row_number' => 0,
            'column_name' => 'SYSTEM_ERROR',
            'error_message' => 'Error crítico en validación de estructura: '.$exception->getMessage(),
            'error_type' => 'system_structure_error',
            'original_data' => null,
            'timestamp' => now()->toISOString(),
        ]));
    }
}
