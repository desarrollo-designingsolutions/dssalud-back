<?php

namespace App\Helpers\FilingOld;

use App\Helpers\Common\ErrorCollector;
use App\Helpers\Constants;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class ZipHelper
{
    // variables claves para crear el json
    protected static $keyNumFact = Constants::KEY_NUMFACT;

    protected static $keyNumDocumentoIdentificacion = Constants::KEY_NumDocumentoIdentificacion;

    protected static $keyVrServicio = Constants::KEY_VrServicio;

    /**
     * Abre un archivo ZIP y devuelve un array con las rutas y contenidos de los archivos .txt extraídos.
     *
     * @param  string  $fileZip  Ruta relativa del ZIP (relativa a storage/app/)
     * @return array Lista de archivos extraídos con nombre, ruta y contenido, o vacío si falla
     */
    public static function openFileZip($uniqid, $fileZip): array
    {

        $keyErrorRedis = "filingOld:{$uniqid}:errors";

        // Obtener la ruta completa del archivo en el servidor
        $fullZipPath = Storage::disk(Constants::DISK_FILES)->path($fileZip);

        if (! file_exists($fullZipPath)) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'ZIPERROR001',
                'R',
                null,
                basename($fileZip),
                null,
                null,
                null,
                'El archivo ZIP no se encuentra en la ruta especificada. Verifique la ruta y vuelva a intentarlo.'
            );

            return [];
        }

        $zip = new ZipArchive;
        if ($zip->open($fullZipPath) !== true) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'ZIPERROR002',
                'R',
                null,
                basename($fileZip),
                null,
                null,
                null,
                'No se pudo abrir el archivo ZIP. Asegúrese de que no esté corrupto o protegido.'
            );

            return [];
        }

        $tempDirectory = storage_path('app/public/temp_zip_'.uniqid());
        if (! mkdir($tempDirectory, 0755, true)) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'ZIPERROR003',
                'R',
                null,
                basename($fileZip),
                null,
                null,
                null,
                'No se pudo crear el directorio temporal para extraer el ZIP. Verifique los permisos del sistema.'
            );
            $zip->close();

            return [];
        }

        $zip->extractTo($tempDirectory);
        $archivos = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if (substr($filename, -1) === '/') {
                continue;
            }

            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $rutaTemporal = $tempDirectory.'/'.$filename;

            if ($extension === 'txt') {
                // Obtener el contenido directamente desde el ZIP
                $contenido = $zip->getFromName($filename);
                if ($contenido === false) {
                    ErrorCollector::addError(
                        $keyErrorRedis,
                        'ZIPERROR005',
                        'R',
                        null,
                        $filename,
                        null,
                        null,
                        null,
                        'No se pudo leer el contenido del archivo '.$filename.' dentro del ZIP.'
                    );

                    continue;
                }

                // Verificar y convertir a UTF-8 si es necesario
                if (! mb_check_encoding($contenido, 'UTF-8')) {
                    $contenido = mb_convert_encoding($contenido, 'UTF-8', 'ISO-8859-1');
                }

                // Contar las líneas en el campo 'content'
                $countRows = count(explode("\n", $contenido));
                $contentDataArray = explode("\n", $contenido);

                $archivos[] = [
                    'name' => $filename,
                    'extension' => $extension,
                    'rutaTemporal' => $rutaTemporal, // Ruta del archivo extraído
                    'contentDataArray' => $contentDataArray,         // Contenido del archivo
                    'count_rows' => $countRows,         // Contenido del archivo
                ];
            }
        }

        $zip->close();

        // array_map('unlink', glob("$tempDirectory/*"));
        // rmdir($tempDirectory);

        Redis::set("filingOld:{$uniqid}:tempZip", $tempDirectory);

        if (empty($archivos)) {
            ErrorCollector::addError(
                $keyErrorRedis,
                'ZIPERROR004',
                'R',
                null,
                basename($fileZip),
                null,
                null,
                null,
                'El ZIP no contiene archivos .txt válidos. Asegúrese de incluir archivos de texto.'
            );
        }

        return $archivos;
    }

    /**
     * Construye un array con todos los datos combinados de los archivos del ZIP.
     *
     * @param  array  $files  Lista de archivos extraídos con name y content
     * @return array Datos combinados con AF como base
     */
    public static function buildAllDataTogether($files): array
    {
        $instance = new self; // Crear instancia para acceder a métodos protegidos

        // Mapeo de tipos de archivos y sus respectivos métodos de formato
        $fileTypes = [
            'AF' => 'formatValueAF',
            'AC' => 'formatValueAC',
            'US' => 'formatValueUS',
            'AP' => 'formatValueAP',
            'AM' => 'formatValueAM',
            'AU' => 'formatValueAU',
            'AH' => 'formatValueAH',
            'AN' => 'formatValueAN',
            'AT' => 'formatValueAT',
        ];

        // Inicializar un array para almacenar los datos formateados
        $dataArrays = [];

        // Inicializar todas las claves con arrays vacíos
        foreach ($fileTypes as $type => $method) {
            $dataArrays[$type] = [];
        }

        // Procesar los archivos
        foreach ($files as $file) {
            foreach ($fileTypes as $type => $method) {
                if (stripos($file['name'], $type) !== false) {
                    $dataArrays[$type] = $instance->formatDataTxt($file['contentDataArray'], [$instance, $method]);
                    break; // Salir del bucle interno una vez que se encuentra el tipo
                }
            }
        }

        // Convertir todos los arrays a colecciones
        $dataArrays = array_map(function ($data) {
            return collect($data);
        }, $dataArrays);

        // Mapear los tipos de servicios para aplicar invoiceUserServices
        $serviceTypes = [
            'AC' => 'consultas',
            'AP' => 'procedimientos',
            'AM' => 'medicamentos',
            'AU' => 'urgencias',
            'AH' => 'hospitalizacion',
            'AN' => 'recienNacidos',
            'AT' => 'otrosServicios',
        ];

        $dataArrays['AF'] = $dataArrays['AF']->map(function ($item) use ($dataArrays, $serviceTypes, $instance) {
            foreach ($serviceTypes as $type => $service) {
                // Verificar si la clave existe en $dataArrays antes de usarla
                if (isset($dataArrays[$type])) {
                    $instance->invoiceUserServices($dataArrays[$type], $dataArrays['US'], $item, $service);
                }
            }

            return $item;
        })->toArray();

        return $dataArrays['AF'];
    }

    /**
     * Formatea el contenido de un archivo de texto en un array.
     *
     * @param  string  $contenido  Contenido del archivo
     * @param  callable|null  $function  Función para formatear cada línea
     * @return array Datos formateados
     */
    protected function formatDataTxt($contenido, $function = null): array
    {
        $dataArray = [];
        // $lineas = explode("\n", $contenido);

        $lineas = is_array($contenido) ? $contenido : explode("\n", $contenido);
        foreach ($lineas as $linea) {
            $datos = explode(',', $linea);
            if ($function) {
                $dataArray[] = call_user_func($function, $datos);
            } else {
                $dataArray[] = $datos;
            }
        }

        return $dataArray;
    }

    /**
     * Asocia servicios a usuarios en una factura.
     *
     * @param  \Illuminate\Support\Collection  $dataArray  Colección de datos del servicio
     * @param  \Illuminate\Support\Collection  $dataArrayUS  Colección de usuarios
     * @param  array  &$invoice  Factura a modificar
     * @param  string  $keyService  Clave del servicio
     */
    protected function invoiceUserServices($dataArray, $dataArrayUS, &$invoice, $keyService): void
    {

        $registers = $dataArray->filter(function ($atItem) use ($invoice) {
            return $atItem[self::$keyNumFact] == $invoice[self::$keyNumFact];
        })->values();

        $i = 0;
        foreach ($registers as $key => $value) {
            $usuario = $dataArrayUS->filter(function ($acItem) use ($value) {
                return $acItem[self::$keyNumDocumentoIdentificacion] == $value[self::$keyNumDocumentoIdentificacion];
            })->first();

            $user = collect($invoice['usuarios'])->filter(function ($value) use ($usuario) {
                return $value[self::$keyNumDocumentoIdentificacion] == $usuario[self::$keyNumDocumentoIdentificacion];
            })->values();

            if (count($user) == 0) {
                $invoice['usuarios'][$i] = $usuario;
                $invoice['usuarios'][$i]['servicios'] = [];
            }

            if (isset($invoice['usuarios'][$i]['servicios']) && ! isset($invoice['usuarios'][$i]['servicios'][$keyService])) {
                $invoice['usuarios'][$i]['servicios'][$keyService] = [];
            }

            $dataService = $dataArray->filter(function ($atItem) use ($invoice, $usuario) {
                return $atItem[self::$keyNumFact] == $invoice[self::$keyNumFact] && $atItem[self::$keyNumDocumentoIdentificacion] == $usuario[self::$keyNumDocumentoIdentificacion];
            })->values();

            if (isset($invoice['usuarios'][$i]['servicios'][$keyService]) && count($invoice['usuarios'][$i]['servicios'][$keyService]) == 0) {
                $invoice['usuarios'][$i]['servicios'][$keyService] = $dataService;
            }

            $i++;
        }
    }

    // Funciones de formateo protegidas
    protected function formatValueAT($datos): array
    {
        return [
            self::$keyNumFact => trim($datos[0]),
            'Codigo_del_prestador_de_servicios_de_salud' => trim($datos[1]),
            'Tipo_de_identificacion_del_usuario' => trim($datos[2]),
            self::$keyNumDocumentoIdentificacion => trim($datos[3]),
            'Numero_de_autorizacion' => trim($datos[4]),
            'Tipo_de_servicio' => trim($datos[5]),
            'Codigo_del_servicio' => trim($datos[6]),
            'Nombre_del_servicio' => trim($datos[7]),
            'Cantidad' => trim($datos[8]),
            'Valor_unitario_del_material_e_insumo' => trim($datos[9]),
            self::$keyVrServicio => trim($datos[10]),
        ];
    }

    protected function formatValueAN($datos): array
    {
        return [
            self::$keyNumFact => trim($datos[0]),
            'Codigo_del_prestador_de_servicios_de_salud' => trim($datos[1]),
            'Tipo_de_identificacion_de_la_madre' => trim($datos[2]),
            self::$keyNumDocumentoIdentificacion => trim($datos[3]),
            'Fecha_de_nacimiento_del_recien_nacido' => trim($datos[4]),
            'Hora_de_nacimiento' => trim($datos[5]),
            'Edad_gestacional' => trim($datos[6]),
            'Control_prenatal' => trim($datos[7]),
            'Sexo' => trim($datos[8]),
            'Peso' => trim($datos[9]),
            'Diagnostico_del_recien_nacido' => trim($datos[10]),
            'Causa_basica_de_muerte' => trim($datos[11]),
            'Fecha_de_muerte_del_recien_nacido' => trim($datos[12]),
            'Hora_de_muerte_del_recien_nacido' => trim($datos[13]),
        ];
    }

    protected function formatValueAH($datos): array
    {
        return [
            self::$keyNumFact => trim($datos[0]),
            'Codigo_del_prestador_de_servicios_de_salud' => trim($datos[1]),
            'Tipo_de_identificacion_del_usuario' => trim($datos[2]),
            self::$keyNumDocumentoIdentificacion => trim($datos[3]),
            'Via_de_ingreso_a_la_institucion' => trim($datos[4]),
            'Fecha_de_ingreso_del_usuario_a_la_institucion' => trim($datos[5]),
            'Hora_de_ingreso_del_usuario_a_la_Institucion' => trim($datos[6]),
            'Numero_de_autorizacion' => trim($datos[7]),
            'Causa_externa' => trim($datos[8]),
            'Diagnostico_principal_de_ingreso' => trim($datos[9]),
            'Diagnostico_principal_de_egreso' => trim($datos[10]),
            'Diagnostico_relacionado_Nro_1_de_egreso' => trim($datos[11]),
            'Diagnostico_relacionado_Nro_2_de_egreso' => trim($datos[12]),
            'Diagnostico_relacionado_Nro_3_de_egreso' => trim($datos[13]),
            'Diagnostico_de_la_complicacion' => trim($datos[14]),
            'Estado_a_la_salida' => trim($datos[15]),
            'Diagnostico_de_la_causa_basica_de_muerte' => trim($datos[16]),
            'Fecha_de_egreso_del_usuario_a_la_institucion' => trim($datos[17]),
            'Hora_de_egreso_del_usuario_de_la_institucion' => trim($datos[18]),
        ];
    }

    protected function formatValueAM($datos): array
    {
        return [
            self::$keyNumFact => trim($datos[0]),
            'Codigo_del_prestador_de_servicios_de_salud' => trim($datos[1]),
            'Tipo_de_identificacion_del_usuario' => trim($datos[2]),
            self::$keyNumDocumentoIdentificacion => trim($datos[3]),
            'Numero_de_autorizacion' => trim($datos[4]),
            'Codigo_del_medicamento' => trim($datos[5]),
            'Tipo_de_medicamento' => trim($datos[6]),
            'Nombre_generico_del_medicamento' => trim($datos[7]),
            'Forma_farmaceutica' => trim($datos[8]),
            'Concentracion_del_medicamento' => trim($datos[9]),
            'Unidad_de_medida_del_medicamento' => trim($datos[10]),
            'Numero_de_unidades' => trim($datos[11]),
            'Valor_unitario_de_medicamento' => trim($datos[12]),
            self::$keyVrServicio => trim($datos[13]),
        ];
    }

    protected function formatValueAU($datos): array
    {
        return [
            self::$keyNumFact => trim($datos[0]),
            'Codigo_del_prestador_de_servicios_de_salud' => trim($datos[1]),
            'Tipo_de_identificacion_del_usuario' => trim($datos[2]),
            self::$keyNumDocumentoIdentificacion => trim($datos[3]),
            'Fecha_de_ingreso_del_usuario_a_observacion' => trim($datos[4]),
            'Hora_de_ingreso_del_usuario_a_observacion' => trim($datos[5]),
            'Numero_de_autorizacion' => trim($datos[6]),
            'Causa_externa' => trim($datos[7]),
            'Diagnostico_a_la_salida' => trim($datos[8]),
            'Diagnostico_relacionado_Nro_1_a_la_salida' => trim($datos[9]),
            'Diagnostico_relacionado_Nro_2_a_la_salida' => trim($datos[10]),
            'Diagnostico_relacionado_Nro_3_a_la_salida' => trim($datos[11]),
            'Destino_del_usuario_a_la_salida_de_observacion' => trim($datos[12]),
            'Estado_a_la_salida' => trim($datos[13]),
            'Causa_basica_de_muerte_en_urgencias' => trim($datos[14]),
            'Fecha_de_la_salida_del_usuario_en_observacion' => trim($datos[15]),
            'Hora_de_la_salida_del_usuario_en_observacion' => trim($datos[16]),
        ];
    }

    protected function formatValueAP($datos): array
    {
        return [
            self::$keyNumFact => trim($datos[0]),
            'Codigo_del_prestador_de_servicios_de_salud' => trim($datos[1]),
            'Tipo_de_identificacion_del_usuario' => trim($datos[2]),
            self::$keyNumDocumentoIdentificacion => trim($datos[3]),
            'Fecha_del_procedimiento' => trim($datos[4]),
            'Numero_de_autorizacion' => trim($datos[5]),
            'Codigo_del_procedimiento' => trim($datos[6]),
            'Ambito_de_realizacion_del_procedimiento' => trim($datos[7]),
            'Finalidad_del_procedimiento' => trim($datos[8]),
            'Personal_que_atiende' => trim($datos[9]),
            'Diagnostico_principal' => trim($datos[10]),
            'Diagnostico_relacionado' => trim($datos[11]),
            'Complicacion' => trim($datos[12]),
            'Forma_de_realizacion_del_acto_quirurgico' => trim($datos[13]),
            self::$keyVrServicio => trim($datos[14]),
        ];
    }

    protected function formatValueUS($datos): array
    {
        return [
            'Tipo_de_identificacion_del_usuario' => trim($datos[0]),
            self::$keyNumDocumentoIdentificacion => trim($datos[1]),
            'Codigo_entidad_administradora' => trim($datos[2]),
            'Tipo_de_usuario' => trim($datos[3]),
            'Primer_apellido_del_usuario' => trim($datos[4]),
            'Segundo_apellido_del_usuario' => trim($datos[5]),
            'Primer_nombre_del_usuario' => trim($datos[6]),
            'Segundo_nombre_del_usuario' => trim($datos[7]),
            'Edad' => trim($datos[8]),
            'Unidad_de_medida_de_la_edad' => trim($datos[9]),
            'Sexo' => trim($datos[10]),
            'Codigo_del_departamento_de_residencia_habitual' => trim($datos[11]),
            'Codigo_del_municipio_de_residencia_habitual' => trim($datos[12]),
            'Zona_de_residencia_habitual' => trim($datos[13]),
        ];
    }

    protected function formatValueAC($datos): array
    {
        return [
            self::$keyNumFact => trim($datos[0]),
            'Codigo_del_prestador_de_servicios_de_salud' => trim($datos[1]),
            'Tipo_de_identificacion_del_usuario' => trim($datos[2]),
            self::$keyNumDocumentoIdentificacion => trim($datos[3]),
            'Fecha_de_la_consulta' => trim($datos[4]),
            'Numero_de_autorizacion' => trim($datos[5]),
            'Codigo_de_la_consulta' => trim($datos[6]),
            'Finalidad_de_la_consulta' => trim($datos[7]),
            'Causa_externa' => trim($datos[8]),
            'Codigo_de_diagnostico_principal' => trim($datos[9]),
            'Codigo_del_diagnostico_relacionado_No_1' => trim($datos[10]),
            'Codigo_del_diagnostico_relacionado_No_2' => trim($datos[11]),
            'Codigo_del_diagnostico_relacionado_No_3' => trim($datos[12]),
            'Tipo_de_diagnostico_principal' => trim($datos[13]),
            self::$keyVrServicio => trim($datos[14]),
            'Valor_de_la_cuota_moderadora' => trim($datos[15]),
            'Valor_neto_a_pagar' => trim($datos[16]),
        ];
    }

    protected function formatValueAF($datos): array
    {
        return [
            'Codigo_del_prestador_de_servicios_de_salud' => trim($datos[0]),
            'Razon_social_o_apellidos_y_nombre_del_prestador_de_servicios_de_salud' => trim($datos[1]),
            'Tipo_de_identificacion_del_prestador_de_servicios_de_salud' => trim($datos[2]),
            'Numero_de_identificacion_del_prestador' => trim($datos[3]),
            self::$keyNumFact => trim($datos[4]),
            'Fecha_de_expedicion_de_la_factura' => trim($datos[5]),
            'Fecha_de_inicio' => trim($datos[6]),
            'Fecha_final' => trim($datos[7]),
            'Codigo_entidad_administradora' => trim($datos[8]),
            'Nombre_entidad_administradora' => trim($datos[9]),
            'Numero_del_contrato' => trim($datos[10]),
            'Plan_de_beneficios' => trim($datos[11]),
            'Numero_de_la_poliza' => trim($datos[12]),
            'Valor_total_del_pago_compartido_copago' => trim($datos[13]),
            'Valor_de_la_comision' => trim($datos[14]),
            'Valor_total_de_descuentos' => trim($datos[15]),
            'Valor_neto_a_pagar_por_la_entidad_contratante' => trim($datos[16]),
            'usuarios' => [],
        ];
    }
}
