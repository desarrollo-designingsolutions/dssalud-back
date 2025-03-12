<?php

namespace App\Helpers\FilingOld;

use App\Helpers\Common\ErrorCollector;
use App\Helpers\Constants;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class ZipHelper
{
    /**
     * Abre un archivo ZIP y devuelve un array con las rutas y contenidos de los archivos .txt extraídos.
     *
     * @param string $fileZip Ruta relativa del ZIP (relativa a storage/app/)
     * @return array Lista de archivos extraídos con nombre, ruta y contenido, o vacío si falla
     */
    public static function openFileZip($uniqid, $fileZip): array
    {

        $keyErrorRedis = 'filingOld:{$filing->id}:errors';

        // Obtener la ruta completa del archivo en el servidor
        $fullZipPath = Storage::disk(Constants::DISK_FILES)->path($fileZip);

        if (!file_exists($fullZipPath)) {
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

        $zip = new ZipArchive();
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

        $tempDirectory = storage_path('app/temp_zip_' . $uniqid);
        if (!mkdir($tempDirectory, 0755, true)) {
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
            $rutaTemporal = $tempDirectory . '/' . $filename;

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
                        'No se pudo leer el contenido del archivo ' . $filename . ' dentro del ZIP.'
                    );
                    continue;
                }

                // Verificar y convertir a UTF-8 si es necesario
                if (!mb_check_encoding($contenido, 'UTF-8')) {
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
     * @param array $files Lista de archivos extraídos con name y content
     * @return array Datos combinados con AF como base
     */
    public static function buildAllDataTogether($files): array
    {
        $instance = new self(); // Crear instancia para acceder a métodos protegidos

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
                    $dataArrays[$type] = $instance->formatDataTxt($file['content'], [$instance, $method]);
                    $instance->agregarNumeracion($dataArrays[$type], $file['name']);
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

        return [
            'data' => $dataArrays['AF'],
        ];
    }

    /**
     * Formatea el contenido de un archivo de texto en un array.
     *
     * @param string $contenido Contenido del archivo
     * @param callable|null $function Función para formatear cada línea
     * @return array Datos formateados
     */
    protected function formatDataTxt($contenido, $function = null): array
    {
        $dataArray = [];
        $lineas = explode("\n", $contenido);

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
     * Agrega numeración y nombre del archivo a los elementos del array.
     *
     * @param array &$array Array de datos a modificar
     * @param string $file_name Nombre del archivo
     */
    protected function agregarNumeracion(&$array, $file_name): void
    {
        foreach ($array as $key => &$elemento) {
            $elemento['row'] = $key + 1;
            $elemento['file_name'] = $file_name;
        }
    }

    /**
     * Asocia servicios a usuarios en una factura.
     *
     * @param \Illuminate\Support\Collection $dataArray Colección de datos del servicio
     * @param \Illuminate\Support\Collection $dataArrayUS Colección de usuarios
     * @param array &$invoice Factura a modificar
     * @param string $keyService Clave del servicio
     */
    protected function invoiceUserServices($dataArray, $dataArrayUS, &$invoice, $keyService): void
    {
        $registers = $dataArray->filter(function ($atItem) use ($invoice) {
            return $atItem['numFEVPagoModerador'] == $invoice['numFactura'];
        })->values();

        $i = 0;
        foreach ($registers as $key => $value) {
            $usuario = $dataArrayUS->filter(function ($acItem) use ($value) {
                return $acItem['numDocumentoIdentificacion'] == $value['numDocumentoIdentificacion'];
            })->first();

            $user = collect($invoice['usuarios'])->filter(function ($value) use ($usuario) {
                return $value['numDocumentoIdentificacion'] == $usuario['numDocumentoIdentificacion'];
            })->values();

            if (count($user) == 0) {
                $invoice['usuarios'][$i] = $usuario;
                $invoice['usuarios'][$i]['servicios'] = [];
            }

            if (isset($invoice['usuarios'][$i]['servicios']) && !isset($invoice['usuarios'][$i]['servicios'][$keyService])) {
                $invoice['usuarios'][$i]['servicios'][$keyService] = [];
            }

            $dataService = $dataArray->filter(function ($atItem) use ($invoice, $usuario) {
                return $atItem['numFEVPagoModerador'] == $invoice['numFactura'] && $atItem['numDocumentoIdentificacion'] == $usuario['numDocumentoIdentificacion'];
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
            'codPrestador' => trim($datos[1]),
            'numAutorizacion' => trim($datos[4]),
            'idMIPRES' => null,
            'fechaSuministroTecnologia' => null,
            'tipoOS' => trim($datos[5]),
            'codTecnologiaSalud' => trim($datos[6]),
            'nomTecnologiaSalud' => trim($datos[7]),
            'cantidadOS' => trim($datos[8]),
            'tipoDocumentoIdentificacion' => trim($datos[2]),
            'numDocumentoIdentificacion' => trim($datos[3]),
            'vrUnitOS' => trim($datos[9]),
            'vrServicio' => trim($datos[10]),
            'valorPagoModerador' => null,
            'numFEVPagoModerador' => trim($datos[0]),
            'consecutivo' => null,
            'conceptoRecaudo' => null,
        ];
    }

    protected function formatValueAN($datos): array
    {
        return [
            'codPrestador' => trim($datos[1]),
            'tipoDocumentoIdentificacion' => trim($datos[2]),
            'numDocumentoIdentificacion' => trim($datos[3]),
            'fechaNacimiento' => transformDate(trim($datos[4])),
            'edadGestacional' => trim($datos[6]),
            'numConsultasCPrenatal' => trim($datos[7]),
            'codSexoBiologico' => trim($datos[8]),
            'peso' => trim($datos[9]),
            'codDiagnosticoPrincipal' => trim($datos[10]),
            'condicionDestinoUsuarioEgreso' => null,
            'codDiagnosticoCausaMuerte' => trim($datos[11]),
            'fechaEgreso' => null,
            'consecutivo' => null,
            'numFEVPagoModerador' => trim($datos[0]),
        ];
    }

    protected function formatValueAH($datos): array
    {
        return [
            'codPrestador' => trim($datos[1]),
            'viaIngresoServicioSalud' => trim($datos[4]),
            'fechaInicioAtencion' => null,
            'numAutorizacion' => trim($datos[7]),
            'causaMotivoAtencion' => trim($datos[8]),
            'codDiagnosticoPrincipal' => trim($datos[9]),
            'codDiagnosticoPrincipalE' => trim($datos[10]),
            'codDiagnosticoRelacionadoE1' => trim($datos[11]),
            'codDiagnosticoRelacionadoE2' => trim($datos[12]),
            'codDiagnosticoRelacionadoE3' => trim($datos[13]),
            'codComplicacion' => trim($datos[14]),
            'condicionDestinoUsuarioEgreso' => trim($datos[15]),
            'codDiagnosticoCausaMuerte' => trim($datos[16]),
            'fechaEgreso' => null,
            'consecutivo' => null,
            'numDocumentoIdentificacion' => trim($datos[3]),
            'numFEVPagoModerador' => trim($datos[0]),
        ];
    }

    protected function formatValueAM($datos): array
    {
        return [
            'codPrestador' => trim($datos[1]),
            'numAutorizacion' => trim($datos[4]),
            'idMIPRES' => null,
            'fechaDispensAdmon' => null,
            'codDiagnosticoPrincipal' => null,
            'codDiagnosticoRelacionado' => null,
            'tipoMedicamento' => trim($datos[6]),
            'codTecnologiaSalud' => trim($datos[5]),
            'nomTecnologiaSalud' => trim($datos[7]),
            'concentracionMedicamento' => trim($datos[9]),
            'unidadMedida' => trim($datos[10]),
            'formaFarmaceutica' => trim($datos[8]),
            'unidadMinDispensa' => trim($datos[10]),
            'cantidadMedicamento' => trim($datos[11]),
            'diasTratamiento' => null,
            'tipoDocumentoIdentificacion' => trim($datos[2]),
            'numDocumentoIdentificacion' => trim($datos[3]),
            'vrUnitMedicamento' => trim($datos[12]),
            'vrServicio' => trim($datos[13]),
            'valorPagoModerador' => null,
            'numFEVPagoModerador' => trim($datos[0]),
            'consecutivo' => null,
            'conceptoRecaudo' => null,
        ];
    }

    protected function formatValueAU($datos): array
    {
        return [
            'codPrestador' => trim($datos[1]),
            'fechaInicioAtencion' => null,
            'causaMotivoAtencion' => trim($datos[7]),
            'codDiagnosticoPrincipal' => trim($datos[8]),
            'codDiagnosticoPrincipalE' => trim($datos[8]),
            'codDiagnosticoRelacionadoE1' => trim($datos[9]),
            'codDiagnosticoRelacionadoE2' => trim($datos[10]),
            'codDiagnosticoRelacionadoE3' => trim($datos[11]),
            'condicionDestinoUsuarioEgreso' => trim($datos[12]) . ' ' . trim($datos[13]),
            'codDiagnosticoCausaMuerte' => trim($datos[14]),
            'fechaEgreso' => null,
            'consecutivo' => null,
            'numFEVPagoModerador' => trim($datos[0]),
            'numDocumentoIdentificacion' => trim($datos[3]),
        ];
    }

    protected function formatValueAP($datos): array
    {
        return [
            'codPrestador' => trim($datos[1]),
            'fechaInicioAtencion' => null,
            'idMIPRES' => null,
            'numAutorizacion' => trim($datos[5]),
            'codProcedimiento' => trim($datos[6]),
            'viaIngresoServicioSalud' => trim($datos[7]),
            'modalidadGrupoServicioTecSal' => null,
            'grupoServicios' => null,
            'codServicio' => null,
            'finalidadTecnologiaSalud' => trim($datos[8]),
            'tipoDocumentoIdentificacion' => trim($datos[2]),
            'numDocumentoIdentificacion' => trim($datos[3]),
            'codDiagnosticoPrincipal' => trim($datos[9]),
            'codDiagnosticoRelacionado' => trim($datos[10]),
            'codComplicacion' => trim($datos[11]),
            'vrServicio' => trim($datos[14]),
            'valorPagoModerador' => null,
            'numFEVPagoModerador' => trim($datos[0]),
            'consecutivo' => null,
            'conceptoRecaudo' => null,
        ];
    }

    protected function formatValueUS($datos): array
    {
        return [
            'tipoDocumentoIdentificacion' => trim($datos[0]),
            'numDocumentoIdentificacion' => trim($datos[1]),
            'tipoUsuario' => trim($datos[3]),
            'fechaNacimiento' => null,
            'codSexo' => trim($datos[10]),
            'codPaisResidencia' => null,
            'codMunicipioResidencia' => trim($datos[12]),
            'codZonaTerritorialResidencia' => transformCodZonaTerritorialResidencia(trim($datos[13])),
            'incapacidad' => null,
            'consecutivo' => null,
            'codPaisOrigen' => null,
        ];
    }

    protected function formatValueAC($datos): array
    {
        return [
            'codPrestador' => trim($datos[1]),
            'fechaInicioAtencion' => null,
            'numAutorizacion' => trim($datos[5]),
            'codConsulta' => trim($datos[6]),
            'modalidadGrupoServicioTecSal' => null,
            'grupoServicios' => null,
            'codServicio' => null,
            'finalidadTecnologiaSalud' => trim($datos[7]),
            'causaMotivoAtencion' => trim($datos[8]),
            'codDiagnosticoPrincipal' => trim($datos[9]),
            'codDiagnosticoRelacionado1' => trim($datos[10]),
            'codDiagnosticoRelacionado2' => trim($datos[11]),
            'codDiagnosticoRelacionado3' => trim($datos[12]),
            'tipoDiagnosticoPrincipal' => trim($datos[13]),
            'tipoDocumentoIdentificacion' => trim($datos[2]),
            'numDocumentoIdentificacion' => trim($datos[3]),
            'vrServicio' => trim($datos[14]),
            'valorPagoModerador' => trim($datos[15]),
            'numFEVPagoModerador' => trim($datos[0]),
            'consecutivo' => null,
            'conceptoRecaudo' => null,

            'Número de la factura' => trim($datos[0]),
            'Código del prestador de servicios de salud' => trim($datos[0]),
            'Tipo de identificación del usuario' => trim($datos[0]),
            'Número de identificación del usuario en el sistema' => trim($datos[0]),
            'Fecha de la consulta' => trim($datos[0]),
            'Número de autorización' => trim($datos[0]),
            'Código de la consulta' => trim($datos[0]),
            'Finalidad de la consulta' => trim($datos[0]),
            'Causa externa' => trim($datos[0]),
            'codigo de diagnostico principal' => trim($datos[0]),
            'Código del diagnóstico relacionado No. 1' => trim($datos[0]),
            'Código del diagnóstico relacionado No. 2' => trim($datos[0]),
            'Código del diagnóstico relacionado No. 3' => trim($datos[0]),
            'Tipo de diagnóstico principal' => trim($datos[0]),
            'Valor de la consulta' => trim($datos[0]),
            'Valor de la cuota moderadora' => trim($datos[0]),
            'Valor neto a pagar' => trim($datos[0]),

            'consecutivo' => null,


        ];
    }

    protected function formatValueAF($datos): array
    {
        return [
            "Código del prestador de servicios de salud" => trim($datos[0]),
            "Razón social o apellidos y nombre del prestador de servicios de salud" => trim($datos[1]),
            "Tipo de identificación del prestador de servicios de salud" => trim($datos[2]),
            "Número de identificación del prestador" => trim($datos[3]),
            "Número de la factura" => trim($datos[4]),
            "Fecha de expedición de la factura" => trim($datos[5]),
            "Fecha de inicio" => trim($datos[6]),
            "Fecha final" => trim($datos[7]),
            "Código entidad administradora" => trim($datos[8]),
            "Nombre entidad administradora" => trim($datos[9]),
            "Número del contrato" => trim($datos[10]),
            "Plan de beneficios" => trim($datos[11]),
            "Número de la póliza" => trim($datos[12]),
            "Valor total del pago compartido (copago)" => trim($datos[13]),
            "Valor de la comisión" => trim($datos[14]),
            "Valor total de descuentos" => trim($datos[15]),
            "Valor neto a pagar por la entidad contratante" => trim($datos[16]),
            'usuarios' => [],
        ];
    }
}
