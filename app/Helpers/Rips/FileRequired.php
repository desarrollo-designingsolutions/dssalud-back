<?php

use App\Enums\StatusInvoiceEnum;
use App\Enums\StatusRipsEnum;
use App\Exports\Rips\RipXlsExport;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Rip;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

function openFileZip($fileZip, $company_id = null)
{
    $fileZip = public_path('storage/'.$fileZip);

    $zip = new ZipArchive;
    if ($zip->open($fileZip) === true) {

        // Directorio temporal para extraer los archivos del ZIP
        $tempDirectory = storage_path('app/temp_zip');
        // $tempDirectory = public_path('storage/companies/company_' . $company_id); // storage_path('app/temp_zip');
        if (! is_dir($tempDirectory)) {
            mkdir($tempDirectory, 0777, true);
        }

        $archivos = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $contenido = $zip->getFromName($filename);

            $rutaTemporal = $tempDirectory.'/'.$filename;
            // Extraer el archivo del ZIP
            $zip->extractTo($tempDirectory, $filename);

            if ($extension == 'txt') {
                // Verificar y convertir la codificación a UTF-8 si es necesario
                if (! mb_check_encoding($contenido, 'UTF-8')) {
                    // Proporciona la codificación de caracteres de origen si la conoces (por ejemplo, ISO-8859-1).
                    $contenido = mb_convert_encoding($contenido, 'UTF-8', 'ISO-8859-1');
                }
            }

            $archivos[] = [
                'name' => $filename,
                'extension' => $extension,
                'content' => $contenido,
                'rutaTemporal' => $rutaTemporal,
            ];
        }
        $zip->close();

        return $archivos;
    } else {
        return false; // O manejar el error de apertura del archivo ZIP de la forma que desees.
    }
}
function validationFileZip($rip, &$errorMessages)
{
    $allowedInitialer = [
        [
            'type' => 'AC',
            'cant' => 17,
        ],
        [
            'type' => 'AF',
            'cant' => 17,
        ],
        [
            'type' => 'AH',
            'cant' => 19,
        ],
        [
            'type' => 'AM',
            'cant' => 14,
        ],
        [
            'type' => 'AN',
            'cant' => 14,
        ],
        [
            'type' => 'AP',
            'cant' => 15,
        ],
        [
            'type' => 'AT',
            'cant' => 11,
        ],
        [
            'type' => 'AU',
            'cant' => 17,
        ],
        [
            'type' => 'US',
            'cant' => 14,
        ],
    ];
    // Inicializa variables para contar archivos que cumplen con los requisitos
    $countAf = 0;
    $countUs = 0;
    $countAc = 0;
    $countAt = 0;
    $countAp = 0;
    $countAm = 0;
    $countOther = 0;

    if ($rip->path_zip) {
        $path_zip = $rip->path_zip;

        // Obtener solo el nombre del archivo de la ruta
        $nameZip = basename($path_zip);

        // Obtener la extensión del archivo
        $extension = pathinfo($nameZip, PATHINFO_EXTENSION);

        // Verifica si la extensión es .zip
        if ($extension === 'zip') {
            $zip = new \ZipArchive;

            $zipPath = public_path('storage/'.$path_zip);

            if ($zip->open($zipPath) === true) {

                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $nameFile = $zip->getNameIndex($i);

                    $extensionArchivo = pathinfo($nameFile, PATHINFO_EXTENSION);

                    // validar que todos los archivos sean txt sino se muere
                    if (strtolower($extensionArchivo) !== 'txt') {
                        $errorMessages[] = [
                            'file' => $nameZip,
                            'row' => null,
                            'column' => null,
                            'data' => $extensionArchivo,
                            'error' => 'Existen archivos que no son TXT.',
                        ];

                        return false;
                    }

                    if (strtolower($extensionArchivo) === 'txt') {
                        $nombreSinExtension = pathinfo($nameFile, PATHINFO_FILENAME);

                        // Verifica si el nombre del archivo comienza con alguna de las palabras permitidas
                        foreach ($allowedInitialer as $inicialPermitida) {

                            if (strpos($nombreSinExtension, $inicialPermitida['type']) === 0) {

                                // validamos la cantidad de elementos que tenga cada registro de cada archivo
                                $contenido = $zip->getFromName($nameFile);

                                $contenido = mb_convert_encoding($contenido, 'UTF-8', 'UTF-8');

                                if (isset($contenido) && ! empty(trim($contenido))) {

                                    $dataArrayTxt = formatDataTxt($contenido);
                                    validarLongitudElementos($dataArrayTxt, $nameFile, $inicialPermitida['cant'], $errorMessages);

                                    if ($inicialPermitida['type'] === 'AF') {
                                        $countAf++;
                                    } elseif ($inicialPermitida['type'] === 'US') {
                                        $countUs++;
                                    } elseif ($inicialPermitida['type'] === 'AC') {
                                        $countAc++;
                                    } elseif ($inicialPermitida['type'] === 'AT') {
                                        $countAt++;
                                    } elseif ($inicialPermitida['type'] === 'AP') {
                                        $countAp++;
                                    } elseif ($inicialPermitida['type'] === 'AM') {
                                        $countAm++;
                                    } else {
                                        $countOther++;
                                    }
                                    break; // Sale del bucle al encontrar una coincidencia
                                }
                            }
                        }
                    }
                }

                $zip->close();

                // Verifica que se cumplan los requisitos
                if (($countAf >= 1 && $countUs >= 1 && ($countAc >= 1 || $countAp >= 1 || $countAm >= 1 || $countAt >= 1))) {
                    return true; // "El archivo ZIP cumple con los requisitos.";
                } else {
                    $errorMessages[] = [
                        'file' => $nameZip,
                        'row' => null,
                        'column' => null,
                        'data' => null,
                        'error' => 'El archivo ZIP no cumple con los requisitos de nombres y cantidad de archivos .txt.',
                    ];

                    return false;
                }

                if ($zip->numFiles < 3) {
                    $errorMessages[] = [
                        'file' => $nameZip,
                        'row' => null,
                        'column' => null,
                        'data' => null,
                        'error' => 'El archivo ZIP debe contener minimo 3 archivos.',
                    ];

                    return false;
                }
                if ($zip->numFiles > 10) {
                    $errorMessages[] = [
                        'file' => $nameZip,
                        'row' => null,
                        'column' => null,
                        'data' => null,
                        'error' => 'El archivo ZIP debe contener maximo 10 archivos.',
                    ];

                    return false;
                }
            } else {
                $errorMessages[] = [
                    'file' => $nameZip,
                    'row' => null,
                    'column' => null,
                    'data' => null,
                    'error' => 'No se pudo abrir el archivo ZIP.',
                    'data1' => storage_path($nameZip),
                    'data2' => public_path(storage_path($nameZip)),
                    'data3' => '/'.$nameZip,
                ];

                return false;
            }
        } else {
            $errorMessages[] = [
                'file' => $nameZip,
                'row' => null,
                'column' => null,
                'data' => null,
                'error' => 'El archivo no es un archivo .zip.',
            ];

            return false;
        }
    } else {
        $errorMessages[] = [
            'file' => null,
            'row' => null,
            'column' => null,
            'data' => null,
            'error' => 'No se ha subido ningún archivo.',
        ];

        return false;
    }
}

function buildAllDataTogether($files)
{
    $dataArrayAF = [];
    $dataArrayAC = [];
    $dataArrayUS = [];
    $dataArrayAP = [];
    $dataArrayAM = [];
    $dataArrayAU = [];
    $dataArrayAH = [];
    $dataArrayAN = [];
    $dataArrayAT = [];

    foreach ($files as $key => $value) {

        if (stripos($value['name'], 'AF') !== false) {
            $dataArrayAF = formatDataTxt($value['content'], 'formatValueAF');
            agregarNumeracion($dataArrayAF, $value['name']);
        }
        if (stripos($value['name'], 'AC') !== false) {
            $dataArrayAC = formatDataTxt($value['content'], 'formatValueAC');
            agregarNumeracion($dataArrayAC, $value['name']);
        }
        if (stripos($value['name'], 'US') !== false) {
            $dataArrayUS = formatDataTxt($value['content'], 'formatValueUS');
            agregarNumeracion($dataArrayUS, $value['name']);
        }
        if (stripos($value['name'], 'AP') !== false) {
            $dataArrayAP = formatDataTxt($value['content'], 'formatValueAP');
            agregarNumeracion($dataArrayAP, $value['name']);
        }
        if (stripos($value['name'], 'AM') !== false) {
            $dataArrayAM = formatDataTxt($value['content'], 'formatValueAM');
            agregarNumeracion($dataArrayAM, $value['name']);
        }
        if (stripos($value['name'], 'AU') !== false) {
            $dataArrayAU = formatDataTxt($value['content'], 'formatValueAU');
            agregarNumeracion($dataArrayAU, $value['name']);
        }
        if (stripos($value['name'], 'AH') !== false) {
            $dataArrayAH = formatDataTxt($value['content'], 'formatValueAH');
            agregarNumeracion($dataArrayAH, $value['name']);
        }
        if (stripos($value['name'], 'AN') !== false) {
            $dataArrayAN = formatDataTxt($value['content'], 'formatValueAN');
            agregarNumeracion($dataArrayAN, $value['name']);
        }
        if (stripos($value['name'], 'AT') !== false) {
            $dataArrayAT = formatDataTxt($value['content'], 'formatValueAT');
            agregarNumeracion($dataArrayAT, $value['name']);
        }
    }

    $dataArrayAF = collect($dataArrayAF);
    $dataArrayAC = collect($dataArrayAC);
    $dataArrayUS = collect($dataArrayUS);
    $dataArrayAP = collect($dataArrayAP);
    $dataArrayAM = collect($dataArrayAM);
    $dataArrayAU = collect($dataArrayAU);
    $dataArrayAH = collect($dataArrayAH);
    $dataArrayAN = collect($dataArrayAN);
    $dataArrayAT = collect($dataArrayAT);

    $dataArrayAF = $dataArrayAF->map(function ($item) use ($dataArrayAC, $dataArrayUS, $dataArrayAP, $dataArrayAM, $dataArrayAU, $dataArrayAH, $dataArrayAN, $dataArrayAT) {

        invoiceUserServices($dataArrayAC, $dataArrayUS, $item, 'consultas');

        invoiceUserServices($dataArrayAP, $dataArrayUS, $item, 'procedimientos');

        invoiceUserServices($dataArrayAM, $dataArrayUS, $item, 'medicamentos');

        invoiceUserServices($dataArrayAU, $dataArrayUS, $item, 'urgencias');

        invoiceUserServices($dataArrayAH, $dataArrayUS, $item, 'hospitalizacion');

        invoiceUserServices($dataArrayAN, $dataArrayUS, $item, 'recienNacidos');

        invoiceUserServices($dataArrayAT, $dataArrayUS, $item, 'otrosServicios');

        return $item;
    })->toArray();

    return [
        'data' => $dataArrayAF,
    ];
}

function formatDataTxt($contenido, $function = null)
{
    $dataArray = [];
    // Divide el contenido en líneas
    $lineas = explode("\n", $contenido);

    foreach ($lineas as $linea) {
        // Divide cada línea en función de la coma
        $datos = explode(',', $linea);

        if ($function) {
            // Agrega los datos como un array asociativo al arreglo final
            $dataArray[] = $function($datos);
        } else {
            $dataArray[] = $datos;
        }
    }

    return $dataArray;
}
function agregarNumeracion(&$array, $file_name)
{
    foreach ($array as $key => &$elemento) {
        $elemento['row'] = $key + 1;
        $elemento['file_name'] = $file_name;
    }
}

function invoiceUserServices($dataArray, $dataArrayUS, &$invoice, $keyService)
{
    $registers = $dataArray->filter(function ($atItem) use ($invoice) {
        return $atItem['numFEVPagoModerador'] == $invoice['numFactura'];
    })->values();

    $i = 0;
    foreach ($registers as $key => $value) {
        // Agregar los elementos encontrados a la subcolección 'usuarios'

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

        if (isset($invoice['usuarios'][$i]['servicios']) && ! isset($invoice['usuarios'][$i]['servicios'][$keyService])) {
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

function formatValueAT($datos)
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
function formatValueAN($datos)
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
function formatValueAH($datos)
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
function formatValueAM($datos)
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
function formatValueAU($datos)
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
        'condicionDestinoUsuarioEgreso' => trim($datos[12]).' '.trim($datos[13]),
        'codDiagnosticoCausaMuerte' => trim($datos[14]),
        'fechaEgreso' => null,
        'consecutivo' => null,
        'numFEVPagoModerador' => trim($datos[0]),
        'numDocumentoIdentificacion' => trim($datos[3]),

    ];
}
function formatValueAP($datos)
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
function formatValueUS($datos)
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
function formatValueAC($datos)
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
    ];
}

function formatValueAF($datos)
{
    return [
        'numDocumentoIdObligado' => trim($datos[3]),
        'numFactura' => trim($datos[4]),
        'TipoNota' => null,
        'numNota' => null,
        'usuarios' => [],
    ];
}

function deleteFile($files)
{
    if (count($files) > 0) {
        foreach ($files as $key => $value) {
            unlink($value);
        }
    }
}

function verificarPalabraEnCadena($cadena, $palabra)
{
    // Convierte tanto la cadena como la palabra a minúsculas para hacer la comparación insensible a mayúsculas/minúsculas
    $cadena = strtolower($cadena);
    $palabra = strtolower($palabra);

    // Busca la posición de la palabra en la cadena
    $posicion = strpos($cadena, $palabra);

    // Verifica si la palabra se encontró en la cadena
    if ($posicion !== false) {
        return true; // La palabra se encontró en la cadena
    } else {
        return false; // La palabra no se encontró en la cadena
    }
}

function calcularEdad($fechaNacimiento)
{
    // Parsea la fecha de nacimiento usando Carbon
    $fechaNacimiento = Carbon::parse($fechaNacimiento);

    // Obtiene la fecha actual como un objeto Carbon
    $fechaActual = Carbon::now();

    // Calcula la diferencia en años
    $edad = $fechaActual->diffInYears($fechaNacimiento);

    return $edad;
}

function eliminarKeysRecursivas(&$array, $keysAEliminar)
{
    foreach ($array as $key => &$value) {
        if (is_array($value) || is_object($value)) {
            eliminarKeysRecursivas($value, $keysAEliminar);

            // Eliminar la entrada si es un objeto vacío después de la recursión
            if (empty((array) $value) && is_object($array)) {
                unset($array->$key);
            } elseif (empty($value) && is_array($array)) {
                unset($array[$key]);
            }
        } else {
            if (in_array($key, $keysAEliminar)) {
                unset($array[$key]);
            }
        }
    }
}

function validarLongitudElementos(&$array, $file_name, $cantidadEsperada, &$errorMessages)
{
    foreach ($array as $key => &$elemento) {
        $cantidadActual = count($elemento);
        if ($cantidadActual !== $cantidadEsperada) {
            $errorMessages[] = [
                'file' => $file_name ?? null,
                'row' => $key + 1 ?? null,
                'error' => "El elemento debe tener $cantidadEsperada elementos y tiene $cantidadActual elementos.",
            ];
        }
    }
}

function minimFilesRequired($path, $errors)
{
    $message = 'Mínimo Deben Ser 5 Archivos .txt';

    $directory = opendir(public_path($path));

    $quantity = 0;

    while ($file = readdir($directory)) {
        if (! is_dir(public_path($path.'/'.$file))) {
            $quantity++;
        }
    }

    if ($quantity >= 5) {
        $strings = ['AC', 'AP', 'AH', 'AM', 'AT'];
        $validateErrors = validateFileNames($directory, $strings);

        if (count($validateErrors) > 0) {
            foreach ($validateErrors as $error) {
                $errors[] = $error;
            }
        } else {
            echo 'Todos los archivos están presentes.';
        }
    } else {
        $errors[] = $message;
    }

    return $errors;
}

function validateFileNames($directory, $strings)
{
    $message = 'No Se Encontraron Los Siguientes Archivos ';
    $errors = [];
    $filesRequeridos = [];

    while ($file = readdir($directory)) {
        if (! is_dir($file)) {
            $filename = pathinfo($file, PATHINFO_FILENAME); // Obtener el nombre del archivo sin extensión
            $filename = strtolower($filename); // Convertir el nombre del archivo a minúsculas
            $found = false;

            foreach ($strings as $string) {
                $pattern = '/^'.preg_quote(strtolower($string), '/').'/'; // Expresión regular para coincidir al principio
                if (preg_match($pattern, $filename)) {
                    $found = true;
                    break;
                } else {
                    $filesRequeridos[] = $string;
                }
            }

            if (! $found) {
                $errors[] = $message.$file;
            }
        }
    }

    closedir($directory);

    if (count($filesRequeridos) > 0) {
        $errors[] = $message.implode(',', $filesRequeridos);
    }

    if (count($errors) > 0) {
        echo 'Archivos no encontrados: '.implode(', ', $filesRequeridos);
    } else {
        echo 'Todos los archivos están presentes.';
    }

    return $errors;
}

function openCsv(Request $request)
{
    if ($request->hasFile('csv_file')) {
        $file = $request->file('csv_file');

        // Procesar el archivo CSV con el delimitador detectado
        $csvData = file($file->getRealPath());

        // Nombre del archivo
        $fileName = $file->getClientOriginalName();

        // Obtener los títulos y eliminarlos del array principal
        $keys = str_getcsv(array_shift($csvData), ';');

        // Crear una colección con los datos del CSV junto con los números de fila
        $csvCollection = collect($csvData)->map(function ($line, $index) use ($keys, $fileName) {
            $row = str_getcsv($line, ';');
            $dataWithKeys = array_combine($keys, $row);
            $dataWithKeys['row'] = $index + 2; // Sumar 2 para ajustar al número de fila real
            $dataWithKeys['file'] = $fileName;

            return $dataWithKeys;
        });

        return $csvCollection;
    }
}

function openXls(Request $request)
{
    if ($request->hasFile('xls_file')) {
        $file = $request->file('xls_file');

        // Leer el archivo XLS usando Laravel Excel
        $data = Excel::toArray([], $file);

        // Procesar los datos obtenidos del archivo XLS
        $keys = $data[0][0]; // Los títulos se encuentran en la primera fila
        $excelData = array_slice($data[0], 1); // Eliminar la primera fila (encabezados)

        // Crear una colección con los datos del XLS junto con los números de fila
        $xlsCollection = collect($excelData)->map(function ($row, $index) use ($keys, $file) {
            $dataWithKeys = array_combine($keys, $row);
            $dataWithKeys['row'] = $index + 2; // Sumar 2 para ajustar al número de fila real
            $dataWithKeys['file'] = $file->getClientOriginalName();

            return $dataWithKeys;
        });

        return $xlsCollection;
    }
}

function groupByNumFactura($csvCollection)
{
    // Agrupar por 'num_factura'
    $groupedData = $csvCollection->groupBy('num_factura');

    return $groupedData;
}

function validateGroupedData(Collection $groupedCsvData)
{
    $errorMessages = [];

    foreach ($groupedCsvData as $numFactura => $group) {
        $missingFields = [];
        $requiredFields = [];

        foreach ($group as $row) {
            $validatingFactura = false;
            $validatingUsuario = false;

            // Verifica si los campos requeridos están presentes y agrega mensajes de error si faltan
            if (empty($row['num_factura'])) {
                $errorMessages[] = [
                    'file' => $row['file'],
                    'row' => $row['row'],
                    'column' => 'num_factura',
                    'data' => '',
                    'error' => 'La fila '.$row['row']." tiene 'num_factura' nulo.",
                ];
            }

            if (empty($row['campo'])) {
                $errorMessages[] = [
                    'file' => $row['file'],
                    'row' => $row['row'],
                    'column' => 'campo',
                    'data' => '',
                    'error' => 'La fila '.$row['row']." tiene 'campo' nulo.",
                ];
            }

            if (empty($row['valor'])) {
                $errorMessages[] = [
                    'file' => $row['file'],
                    'row' => $row['row'],
                    'column' => 'valor',
                    'data' => '',
                    'error' => 'La fila '.$row['row']." tiene 'valor' nulo.",
                ];
            }

            // Verificar si se está validando una factura o un usuario
            if (! empty($row['num_factura']) && empty($row['num_identificacion'])) {
                $validatingFactura = true;
            }

            if (! empty($row['num_factura']) && ! empty($row['num_identificacion'])) {
                $validatingUsuario = true;
                $validatingFactura = false;
            }
        }

        // Lógica para determinar campos requeridos para la validación de factura o usuario
        if ($validatingFactura && ! $validatingUsuario) {
            $requiredFields = ['TipoNota', 'numNota'];
        } elseif (! $validatingFactura && $validatingUsuario) {
            $requiredFields = ['fechaNacimiento', 'codPaisResidencia', 'incapacidad'];
        }

        // Verificar si faltan campos requeridos y agregar mensajes de error
        foreach ($requiredFields as $field) {
            $found = false;
            foreach ($group as $row) {
                if ($row['campo'] === $field) {
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                $errorMessage = [
                    'file' => $group[0]['file'],
                    'row' => $group[0]['row'],
                    'column' => $field,
                    'data' => '',
                    'error' => "Falta el campo '{$field}'",
                ];

                if ($validatingFactura) {
                    $errorMessage['error'] .= " en la factura '{$numFactura}'";
                } else {
                    // Aquí deberías reemplazar '{$row['num_identificacion']}' con el dato real de identificación del usuario
                    $errorMessage['error'] .= " en la factura '{$numFactura}' del usuario '{$row['num_identificacion']}'";
                }

                $errorMessage['error'] .= ', fila '.$group[0]['row'].'.';

                $errorMessages[] = $errorMessage;
            }
        }
    }

    return $errorMessages;
}

function processData($build, $groupedData)
{
    // return$groupedData;
    $buildData = json_decode(collect($build), true);

    // recorremos el array agrupado del csv
    foreach ($groupedData as $key => $group) {
        // buscamos la posicion de la factura que estamos recorriendo
        // en el array general de facturas
        $index = collect($buildData)->search(function ($objeto, $clave) use ($key) {
            return $objeto['numFactura'] == $key;
        });

        // si existe la factura
        if ($index !== false) {
            foreach ($group as $row) {

                // Verificar si se está validando una factura o un usuario
                if (! empty($row['num_factura']) && empty($row['num_identificacion'])) {
                    $requiredFields = ['TipoNota', 'numNota'];
                    // recorremos los dos campos obligatorios
                    foreach ($requiredFields as $keyF => $valueF) {
                        // recorremos internamente la factura del csv
                        // si existen los key pasamos la data del csv al build general
                        if ($row['campo'] == $valueF) {
                            if (! empty($row['valor'])) {
                                $buildData[$index][$valueF] = $row['valor'];
                            }
                        }
                    }
                }
                if (! empty($row['num_factura']) && ! empty($row['num_identificacion']) && empty($row['servicio'])) {
                    $requiredFields = ['codPaisOrigen', 'fechaNacimiento', 'codPaisResidencia', 'codZonaTerritorialResidencia', 'incapacidad', 'consecutivo'];
                    // recorremos los dos campos obligatorios
                    foreach ($requiredFields as $keyF => $valueF) {
                        // recorremos internamente la factura del csv
                        // si existen los key pasamos la data del csv al build general
                        if ($row['campo'] == $valueF) {
                            $indexU = collect($buildData[$index]['usuarios'])->search(function ($objeto, $clave) use ($row) {

                                return $objeto['numDocumentoIdentificacion'] == $row['num_identificacion'];
                            });

                            if (! empty($row['valor'])) {

                                $buildData[$index]['usuarios'][$indexU][$valueF] = $row['valor'];
                            }
                        }
                    }
                }
                if (! empty($row['num_factura']) && ! empty($row['num_identificacion']) && $row['servicio'] == 'consultas') {
                    $requiredFields = ['conceptoRecaudo', 'fechaInicioAtencion', 'modalidadGrupoServicioTecSal', 'grupoServicios', 'codServicio', 'tipoDocumentoIdentificacion', 'numDocumentoIdentificacion', 'valorPagoModerador', 'numFEVPagoModerador', 'consecutivo'];
                    // recorremos los dos campos obligatorios
                    foreach ($requiredFields as $keyF => $valueF) {
                        // recorremos internamente la factura del csv
                        // si existen los key pasamos la data del csv al build general
                        if ($row['campo'] == $valueF) {
                            $indexU = collect($buildData[$index]['usuarios'])->search(function ($objeto, $clave) use ($row) {

                                return $objeto['numDocumentoIdentificacion'] == $row['num_identificacion'];
                            });

                            if (! empty($row['valor'])) {
                                $buildData[$index]['usuarios'][$indexU]['servicios']['consultas'][$row['id_servicio'] - 1][$valueF] = $row['valor'];
                            }
                        }
                    }
                }
                if (! empty($row['num_factura']) && ! empty($row['num_identificacion']) && $row['servicio'] == 'procedimientos') {
                    $requiredFields = ['conceptoRecaudo', 'fechaInicioAtencion', 'idMIPRES', 'modalidadGrupoServicioTecSal', 'grupoServicios', 'codServicio', 'tipoDocumentoIdentificacion', 'numDocumentoIdentificacion', 'valorPagoModerador', 'numFEVPagoModerador', 'consecutivo'];
                    // recorremos los dos campos obligatorios
                    foreach ($requiredFields as $keyF => $valueF) {
                        // recorremos internamente la factura del csv
                        // si existen los key pasamos la data del csv al build general
                        if ($row['campo'] == $valueF) {
                            $indexU = collect($buildData[$index]['usuarios'])->search(function ($objeto, $clave) use ($row) {

                                return $objeto['numDocumentoIdentificacion'] == $row['num_identificacion'];
                            });

                            if (! empty($row['valor'])) {
                                $buildData[$index]['usuarios'][$indexU]['servicios']['procedimientos'][$row['id_servicio'] - 1][$valueF] = $row['valor'];
                            }
                        }
                    }
                }
                if (! empty($row['num_factura']) && ! empty($row['num_identificacion']) && $row['servicio'] == 'medicamentos') {
                    $requiredFields = ['conceptoRecaudo', 'idMIPRES', 'fechaDispensAdmon', 'codDiagnosticoPrincipal', 'codDiagnosticoRelacionado', 'formaFarmaceutica', 'unidadMinDispensa', 'diasTratamiento', 'tipoDocumentoIdentificacion', 'numDocumentoIdentificacion', 'vrUnitMedicamento', 'valorPagoModerador', 'numFEVPagoModerador', 'consecutivo'];
                    // recorremos los dos campos obligatorios
                    foreach ($requiredFields as $keyF => $valueF) {
                        // recorremos internamente la factura del csv
                        // si existen los key pasamos la data del csv al build general
                        if ($row['campo'] == $valueF) {
                            $indexU = collect($buildData[$index]['usuarios'])->search(function ($objeto, $clave) use ($row) {

                                return $objeto['numDocumentoIdentificacion'] == $row['num_identificacion'];
                            });

                            if (! empty($row['valor'])) {
                                $buildData[$index]['usuarios'][$indexU]['servicios']['medicamentos'][$row['id_servicio'] - 1][$valueF] = $row['valor'];
                            }
                        }
                    }
                }
                if (! empty($row['num_factura']) && ! empty($row['num_identificacion']) && $row['servicio'] == 'otrosServicios') {
                    $requiredFields = ['conceptoRecaudo', 'idMIPRES', 'fechaSuministroTecnologia', 'tipoDocumentoIdentificacion', 'numDocumentoIdentificacion', 'valorPagoModerador', 'numFEVPagoModerador', 'consecutivo'];
                    // recorremos los dos campos obligatorios
                    foreach ($requiredFields as $keyF => $valueF) {
                        // recorremos internamente la factura del csv
                        // si existen los key pasamos la data del csv al build general
                        if ($row['campo'] == $valueF) {
                            $indexU = collect($buildData[$index]['usuarios'])->search(function ($objeto, $clave) use ($row) {

                                return $objeto['numDocumentoIdentificacion'] == $row['num_identificacion'];
                            });

                            if (! empty($row['valor'])) {
                                // dd($buildData[$index]['usuarios'][$indexU]['servicios']['otrosServicios'][$row['id_servicio'] - 1][$valueF]);
                                $buildData[$index]['usuarios'][$indexU]['servicios']['otrosServicios'][$row['id_servicio'] - 1][$valueF] = $row['valor'];
                            }
                        }
                    }
                }
                if (! empty($row['num_factura']) && ! empty($row['num_identificacion']) && $row['servicio'] == 'urgencias') {
                    $requiredFields = ['consecutivo', 'fechaInicioAtencion'];
                    // recorremos los dos campos obligatorios
                    foreach ($requiredFields as $keyF => $valueF) {
                        // recorremos internamente la factura del csv
                        // si existen los key pasamos la data del csv al build general
                        if ($row['campo'] == $valueF) {
                            $indexU = collect($buildData[$index]['usuarios'])->search(function ($objeto, $clave) use ($row) {
                                return $objeto['numDocumentoIdentificacion'] == $row['num_identificacion'];
                            });

                            if (! empty($row['valor'])) {
                                $buildData[$index]['usuarios'][$indexU]['servicios']['urgencias'][$row['id_servicio'] - 1][$valueF] = $row['valor'];
                            }
                        }
                    }
                }
                if (! empty($row['num_factura']) && ! empty($row['num_identificacion']) && $row['servicio'] == 'hospitalizacion') {
                    $requiredFields = ['consecutivo', 'fechaInicioAtencion'];
                    // recorremos los dos campos obligatorios
                    foreach ($requiredFields as $keyF => $valueF) {
                        // recorremos internamente la factura del csv
                        // si existen los key pasamos la data del csv al build general
                        if ($row['campo'] == $valueF) {
                            $indexU = collect($buildData[$index]['usuarios'])->search(function ($objeto, $clave) use ($row) {
                                return $objeto['numDocumentoIdentificacion'] == $row['num_identificacion'];
                            });

                            if (! empty($row['valor'])) {
                                $buildData[$index]['usuarios'][$indexU]['servicios']['hospitalizacion'][$row['id_servicio'] - 1][$valueF] = $row['valor'];
                            }
                        }
                    }
                }
                if (! empty($row['num_factura']) && ! empty($row['num_identificacion']) && $row['servicio'] == 'recienNacidos') {
                    $requiredFields = ['tipoDocumentoIdentificacion', 'numDocumentoIdentificacion', 'numConsultasCPrenatal', 'consecutivo'];
                    // recorremos los dos campos obligatorios
                    foreach ($requiredFields as $keyF => $valueF) {
                        // recorremos internamente la factura del csv
                        // si existen los key pasamos la data del csv al build general
                        if ($row['campo'] == $valueF) {
                            $indexU = collect($buildData[$index]['usuarios'])->search(function ($objeto, $clave) use ($row) {
                                return $objeto['numDocumentoIdentificacion'] == $row['num_identificacion'];
                            });

                            if (! empty($row['valor'])) {
                                $buildData[$index]['usuarios'][$indexU]['servicios']['recienNacidos'][$row['id_servicio'] - 1][$valueF] = $row['valor'];
                            }
                        }
                    }
                }
            }
        }
    }

    return $buildData;
}

// function validateNullFileJsonToExcel($ripId, $invoices)
// {
//     try {
//         DB::beginTransaction();
//         $arrayInvoice = [];
//         // return $invoices;

//         foreach ($invoices as $keyI => $invoice) {
//             $requiredFields = ['TipoNota', 'numNota'];

//             // Verificar si faltan campos requeridos
//             $exitoInvoice = verifyNullData($arrayInvoice, $requiredFields, $invoice, $invoice['numFactura']);

//             if ($exitoInvoice && count($invoice['usuarios']) > 0) {

//                 //USUARIOS
//                 foreach ($invoice['usuarios'] as $keyU => $user) {
//                     $requiredFields = ['fechaNacimiento', 'codPaisResidencia', 'codZonaTerritorialResidencia', 'incapacidad', 'consecutivo'];
//                     // Verificar si faltan campos requeridos
//                     $exitoInvoice = verifyNullData($arrayInvoice, $requiredFields, $user, $invoice['numFactura']);
//                     if (!$exitoInvoice) {
//                         break;
//                     }

//                     //CONSULTAS
//                     if (isset($user['servicios']['consultas']) && count($user['servicios']['consultas']) > 0) {
//                         foreach ($user['servicios']['consultas'] as $keyC => $value) {
//                             $requiredFields = ['modalidadGrupoServicioTecSal', 'grupoServicios', 'codServicio', 'tipoDocumentoIdentificacion', 'numDocumentoIdentificacion', 'valorPagoModerador', 'numFEVPagoModerador', 'consecutivo'];
//                             // Verificar si faltan campos requeridos
//                             $exitoInvoice = verifyNullData($arrayInvoice, $requiredFields, $value, $invoice['numFactura']);
//                             if (!$exitoInvoice) {
//                                 break;
//                             }
//                         }
//                         if (!$exitoInvoice) {
//                             break;
//                         }
//                     }

//                     //PROCEDIMIENTOS
//                     if (isset($user['servicios']['procedimientos']) && count($user['servicios']['procedimientos']) > 0) {
//                         // dd($user["servicios"]["procedimientos"]);
//                         foreach ($user['servicios']['procedimientos'] as $keyP => $value) {
//                             $requiredFields = ['idMIPRES', 'modalidadGrupoServicioTecSal', 'grupoServicios', 'codServicio', 'tipoDocumentoIdentificacion', 'numDocumentoIdentificacion', 'valorPagoModerador', 'numFEVPagoModerador', 'consecutivo'];
//                             // Verificar si faltan campos requeridos
//                             $exitoInvoice = verifyNullData($arrayInvoice, $requiredFields, $value, $invoice['numFactura']);
//                             if (!$exitoInvoice) {
//                                 break;
//                             }
//                         }
//                         if (!$exitoInvoice) {
//                             break;
//                         }
//                     }

//                     //MEDICAMENTOS
//                     if (isset($user['servicios']['medicamentos']) && count($user['servicios']['medicamentos']) > 0) {
//                         foreach ($user['servicios']['medicamentos'] as $keyM => $value) {
//                             $requiredFields = ['idMIPRES', 'fechaDispensAdmon', 'codDiagnosticoPrincipal', 'codDiagnosticoRelacionado', 'formaFarmaceutica', 'unidadMinDispensa', 'diasTratamiento', 'tipoDocumentoIdentificacion', 'numDocumentoIdentificacion', 'vrUnitMedicamento', 'valorPagoModerador', 'numFEVPagoModerador', 'consecutivo'];
//                             // Verificar si faltan campos requeridos
//                             $exitoInvoice = verifyNullData($arrayInvoice, $requiredFields, $value, $invoice['numFactura']);
//                             if (!$exitoInvoice) {
//                                 break;
//                             }
//                         }
//                         if (!$exitoInvoice) {
//                             break;
//                         }
//                     }

//                     //URGENCIAS
//                     if (isset($user['servicios']['urgencias']) && count($user['servicios']['urgencias']) > 0) {
//                         foreach ($user['servicios']['urgencias'] as $keyU => $value) {
//                             $requiredFields = ['consecutivo'];
//                             // Verificar si faltan campos requeridos
//                             $exitoInvoice = verifyNullData($arrayInvoice, $requiredFields, $value, $invoice['numFactura']);
//                             if (!$exitoInvoice) {
//                                 break;
//                             }
//                         }
//                         if (!$exitoInvoice) {
//                             break;
//                         }
//                     }

//                     //OTROS SERVICIOS
//                     if (isset($user['servicios']['otrosServicios']) && count($user['servicios']['otrosServicios']) > 0) {
//                         foreach ($user['servicios']['otrosServicios'] as $keyOS => $value) {
//                             $requiredFields = ['idMIPRES', 'fechaSuministroTecnologia', 'tipoDocumentoIdentificacion', 'numDocumentoIdentificacion', 'valorPagoModerador', 'numFEVPagoModerador', 'consecutivo'];
//                             // Verificar si faltan campos requeridos
//                             $exitoInvoice = verifyNullData($arrayInvoice, $requiredFields, $value, $invoice['numFactura']);
//                             if (!$exitoInvoice) {
//                                 break;
//                             }
//                         }
//                         if (!$exitoInvoice) {
//                             break;
//                         }
//                     }

//                     //HOSPITALIZACION
//                     if (isset($user['servicios']['hospitalizacion']) && count($user['servicios']['hospitalizacion']) > 0) {
//                         foreach ($user['servicios']['hospitalizacion'] as $keyH => $value) {
//                             $requiredFields = ['consecutivo'];
//                             // Verificar si faltan campos requeridos
//                             $exitoInvoice = verifyNullData($arrayInvoice, $requiredFields, $value, $invoice['numFactura']);
//                             if (!$exitoInvoice) {
//                                 break;
//                             }
//                         }
//                         if (!$exitoInvoice) {
//                             break;
//                         }
//                     }

//                     //RECIEN NACIDOS
//                     if (isset($user['servicios']['recienNacidos']) && count($user['servicios']['recienNacidos']) > 0) {
//                         foreach ($user['servicios']['recienNacidos'] as $keyRN => $value) {
//                             $requiredFields = ['tipoDocumentoIdentificacion', 'numDocumentoIdentificacion', 'numConsultasCPrenatal', 'consecutivo'];
//                             // Verificar si faltan campos requeridos
//                             $exitoInvoice = verifyNullData($arrayInvoice, $requiredFields, $value, $invoice['numFactura']);
//                             if (!$exitoInvoice) {
//                                 break;
//                             }
//                         }
//                         if (!$exitoInvoice) {
//                             break;
//                         }
//                     }
//                 }
//             }

//             if ($exitoInvoice) {
//                 $modelInvoice = Invoice::where(function ($query) use ($ripId, $invoice) {
//                     $query->where('rip_id', $ripId);
//                     $query->where('num_invoice', $invoice['numFactura']);
//                 })->first();
//                 $modelInvoice->status = "Completed";
//                 $modelInvoice->save();
//             }
//         }

//         //esto es para revisar si alguna factura tiene el estado completo ps el rips pasa a completo tambien y si no a pendiente por xml
//         $model_rips = Rip::with(['invoices'])->find($ripId);

//         $filteredInvoices = $model_rips->invoices->filter(function ($invoice) {
//             return $invoice->status == "Completed";
//         });
//         $filteredInvoicesStatus1 = $model_rips->invoices->filter(function ($invoice) {
//             return $invoice->status == "Incomplete";
//         });

//         $allInvoicesWithState2 = $filteredInvoices->count() === $model_rips->invoices->count();

//         if ($allInvoicesWithState2 && $model_rips->status != "Pending XML") {
//             $model_rips->status = "Pending XML";
//         }

//         $model_rips->successfulInvoices = $filteredInvoices->count();
//         $model_rips->failedInvoices = $filteredInvoicesStatus1->count();
//         $model_rips->save();

//         DB::commit();

//         return $arrayInvoice;
//     } catch (\Throwable $th) {
//         DB::rollBack();
//         throw $th;
//     }
// }

function verifyNullData(&$array, $requiredFields, $value, $element1)
{
    $exito = true;
    foreach ($requiredFields as $field) {
        if (empty($value[$field])) {
            // si una factura tiene false es que no pasa la validacion (osea algun campo configurado no tiene valor)
            $array[$element1] = false;
            $exito = false;
            break;
        }
    }

    return $exito;
}

// function validateRipsStatus($ripId)
// {
//     $rip = Rip::find($ripId);

//     $invoices = $rip->invoices;

//     if ($invoices->where('status', StatusInvoiceEnum::COMPLETED)->count() === $invoices->count() && $invoices->where('xml_status_id', StatusInvoiceEnum::VALIDATED)->count() === $invoices->count()) {
//         $rip->status = StatusRipsEnum::COMPLETED;
//         $rip->save();

//         return;
//     }

//     if ($invoices->where('status', StatusInvoiceEnum::INCOMPLETE)->count() === $invoices->count() && $invoices->where('xml_status_id', StatusInvoiceEnum::ERROR_XML)->count() > 0) {
//         $rip->status = StatusRipsEnum::ERROR_XML;
//         $rip->save();

//         return;
//     }

//     if ($invoices->where('status', StatusInvoiceEnum::INCOMPLETE)->count() === $invoices->count() && $invoices->where('xml_status_id', StatusInvoiceEnum::COMPLETED)->count() < $invoices->count()) {
//         $rip->status = StatusRipsEnum::INCOMPLETE;
//         $rip->save();

//         return;
//     }

//     if ($invoices->where('status', StatusInvoiceEnum::INCOMPLETE)->count() === $invoices->count() && $invoices->where('xml_status_id', StatusInvoiceEnum::VALIDATED)->count() === $invoices->count()) {
//         $rip->status = StatusRipsEnum::PENDING_EXCEL;
//         $rip->save();

//         return;
//     }

//     if ($invoices->where('status', StatusInvoiceEnum::ERROR_EXCEL)->count() > 0 && $invoices->where('xml_status_id', StatusInvoiceEnum::VALIDATED)->count() === $invoices->count()) {
//         $rip->status = StatusRipsEnum::ERROR_EXCEL;
//         $rip->save();

//         return;
//     }

//     if ($invoices->where('status', StatusInvoiceEnum::INCOMPLETE)->count() > 0 && $invoices->where('xml_status_id', StatusInvoiceEnum::VALIDATED)->count() === $invoices->count()) {
//         $rip->status = StatusRipsEnum::PENDING_EXCEL;
//         $rip->save();

//         return;
//     }

//     if ($invoices->where('status', StatusInvoiceEnum::ERROR_EXCEL)->count() > 0) {
//         $rip->status = StatusRipsEnum::ERROR_EXCEL;
//         $rip->save();

//         return;
//     }

//     if ($invoices->where('status', StatusInvoiceEnum::COMPLETED)->count() === $invoices->count() && $invoices->where('xml_status_id', StatusInvoiceEnum::ERROR_XML)->count() === 0) {
//         $rip->status = StatusRipsEnum::PENDING_XML;
//         $rip->save();

//         return;
//     }

//     if ($invoices->where('status', StatusInvoiceEnum::COMPLETED)->count() === $invoices->count() && $invoices->where('xml_status_id', StatusInvoiceEnum::ERROR_XML)->count() > 0) {
//         $rip->status = StatusRipsEnum::ERROR_XML;
//         $rip->save();

//         return;
//     }

//     $rip->status = StatusRipsEnum::COMPLETED;
//     $rip->save();
// }

// function generateDataJsonAndExcel($ripId, $type = "automatic")
// {
//     //generamos el archivo xls con los campos que faltan para todas las facturas

//     $rip = Rip::find($ripId);

//     $jsonContents = [];

//     if (isset($rip->invoices) && count($rip->invoices) > 0) {
//         foreach ($rip->invoices as $key => $value) {
//             $jsonContents[] = openFileJson($value->path_json);
//         }
//     }

//     //EXCELES
//     $nameFile = 'rips_' . $rip->numeration . '.xlsx';
//     $rutaXls = 'companies/company_' . $rip->company_id . '/rips/' . $type . '/rip_' . $rip->numeration . '/' . $nameFile; // Ruta donde se guardará la carpeta
//     Excel::store(new RipXlsExport($jsonContents), $rutaXls, 'public', \Maatwebsite\Excel\Excel::XLSX);

//     //JSONS
//     // Nombre del archivo en el sistema de archivos
//     $nameFile = 'rips_' . $rip->numeration . '.json';
//     // Guarda el JSON en el sistema de archivos usando el disco predeterminado (puede configurar otros discos si es necesario)
//     $ruta = 'companies/company_' . $rip->company_id . '/rips/' . $type . '/rip_' . $rip->numeration . '/' . $nameFile; // Ruta donde se guardará la carpeta
//     Storage::disk('public')->put($ruta, json_encode(array_values($jsonContents))); //guardo el archivo

//     //actualizo el registro del rip en la bd
//     // return sumVrServicioRips($jsonContents);
//     $rip->sumVr = sumVrServicioRips($jsonContents); //actualizo la suma de los campos vrservicio de los servicios
//     $rip->path_json = $ruta;
//     $rip->path_xls = $rutaXls;
//     $rip->save(); //actualizo el registro
//     //guardo el registro del rip en la bd
// }

// suma todos los valores VRSERVICE DE TODAS LAS FACTURAS

// function saveReloadDataRips($data, $updateAll = true)
// {
//     DB::beginTransaction();

//     $rip = Rip::find($data['ripId']);
//     //actualizo el registro del rip en la bd
//     // $rip->sumVr = sumVrServicioRips($data['arraySuccessfulInvoices']);
//     // $rip->save();

//     if ($updateAll) {
//         //tomamos y hacemos un clon exacto de $arraySuccessfulInvoices
//         $buildDataFinal = json_decode(collect($data['arraySuccessfulInvoices']), 1);
//         //le quitamos al array  general las key que no se deben guardar en json
//         eliminarKeysRecursivas($buildDataFinal, ['row', 'file_name']);

//         //quitamos los campos que se necesitan por ahora  (numDocumentoIdentificacion,numFEVPagoModerador de de AH , AN,AU)
//         deleteFieldsPerzonalizedJson($buildDataFinal);

//         //se guarda el xls nuevo, el json general y los json independientes en la bd
//         saveReloadDataInvoices($rip->id, $buildDataFinal);
//     }
//     DB::commit();
// }

// function saveReloadDataInvoices($ripId, $jsonData)
// {
//     //se generan los json y excel por cada factura y se guarda el archivo
//     foreach ($jsonData as $key => $value) {
//         saveReloadDataInvoice($ripId, $value);
//     }
// }

// function saveReloadDataInvoice($ripId, $valueJsonInvoice, $counErrorExcelInvoice = 'funciona')
// {
//     $rip = Rip::find($ripId);

//     $nameFile = $valueJsonInvoice['numFactura'] . '.xlsx';
//     $routeXls = 'companies/company_' . $rip->company_id . '/rips/automatic/rip_' . $rip->numeration . '/invoices/' . $valueJsonInvoice['numFactura'] . '/' . $nameFile; // Ruta donde se guardará la carpeta
//     Excel::store(new RipXlsExport([$valueJsonInvoice]), $routeXls, 'public', \Maatwebsite\Excel\Excel::XLSX);

//     $nameFile = $valueJsonInvoice['numFactura'] . '.json';
//     $routeJson = 'companies/company_' . $rip->company_id . '/rips/automatic/rip_' . $rip->numeration . '/invoices/' . $valueJsonInvoice['numFactura'] . '/' . $nameFile; // Ruta donde se guardará la carpeta
//     Storage::disk('public')->put($routeJson, json_encode($valueJsonInvoice)); //guardo el archivo

//     //se guarda el registro en la BD tabla invoice
//     $invoice = Invoice::where(function ($query) use ($ripId, $valueJsonInvoice) {
//         $query->where('rip_id', $ripId);
//         $query->where('num_invoice', $valueJsonInvoice['numFactura']);
//     })->first();

//     //si la factura no existe la creo una instancia nueva
//     if (!$invoice) {
//         $invoice = Invoice::newModelInstance();
//         $invoice->status = StatusInvoiceEnum::INCOMPLETE;
//         $invoice->xml_status = StatusInvoiceEnum::NOT_VALIDATED;
//     }

//     if ($counErrorExcelInvoice > 0) {
//         $invoice->status = StatusInvoiceEnum::ERROR_EXCEL;
//     }

//     if ($counErrorExcelInvoice == 0) {
//         $invoice->status =  StatusInvoiceEnum::COMPLETED;
//     }

//     if ($counErrorExcelInvoice == 'funciona') {
//         $invoice->status = StatusInvoiceEnum::INCOMPLETE;
//     }

//     $rip = Rip::find($ripId);

//     $invoice->rip_id = $ripId;
//     $invoice->path_json = $routeJson;
//     $invoice->path_excel = $routeXls;
//     $invoice->num_invoice = $valueJsonInvoice['numFactura'];
//     $invoice->sumVr = sumVrServicio($valueJsonInvoice);
//     $invoice->company_id = $rip->company_id;
//     $invoice->save();
// }

function deleteFieldsPerzonalizedJson(&$buildDataFinal)
{
    foreach ($buildDataFinal as &$invoice) {
        foreach ($invoice['usuarios'] as &$user) {
            $services = ['hospitalizacion', 'recienNacidos', 'urgencias'];
            foreach ($services as $service) {
                if (isset($user['servicios'][$service]) && count($user['servicios'][$service]) > 0) {
                    foreach ($user['servicios'][$service] as $keyH => &$value) {
                        unset($value['numDocumentoIdentificacion']);
                        unset($value['numFEVPagoModerador']);
                    }
                }
            }
        }
    }
}

function generateConsecutive(&$buildDataFinal)
{
    foreach ($buildDataFinal as &$invoice) {
        $i = 1;
        foreach ($invoice['usuarios'] as &$user) {
            $user['consecutivo'] = $i;
            $services = ['consultas', 'procedimientos', 'medicamentos', 'urgencias', 'otrosServicios', 'hospitalizacion', 'recienNacidos'];
            foreach ($services as $service) {
                $j = 1;
                if (isset($user['servicios'][$service]) && count($user['servicios'][$service]) > 0) {
                    $user['servicios'][$service] = array_map(function ($value) use (&$j) {
                        $value['consecutivo'] = $j;
                        $j++;

                        return $value;
                    }, $user['servicios'][$service]->toArray());
                }
            }
            $i++;
        }
    }
}

// function validateNitsAndInvoice($invoices, $ripId, $company_id)
// {
//     $rip = Rip::find($ripId);
//     $company = Company::with('nits')->find($company_id);

//     // Verificar si se encontraron el RIP y la empresa
//     if (!$rip || !$company) {
//         return false;
//     }

//     // Obtener los NITs de la empresa
//     $nits = $company->nits->map(function ($value) {
//         if ($value->verification_digit) {
//             $nit = $value->nit . '-' . $value->verification_digit;
//         } else {
//             $nit = $value->nit;
//         }

//         return $nit;
//     })->toArray();

//     // Verificar si al menos una factura no coincide con los NITs de la empresa
//     $fail = false;
//     if (count($nits)) {
//         foreach ($invoices as $invoice) {
//             if (!in_array($invoice['numDocumentoIdObligado'], $nits)) {
//                 $fail = true;
//                 break; // No es necesario continuar verificando las facturas
//             }
//         }
//     }

//     // Actualizar el estado de fail_nits si es necesario
//     if ($fail) {
//         $errorMessages[] = [
//             'validacion' => 'validateNitsAndInvoice',
//             'validacion_type_Y' => 'R',
//             'num_invoice' => null,
//             'file' => null,
//             'row' => null,
//             'column' => null,
//             'data' => null,
//             'error' => 'Usted está intentando validar un NIT que no está registrado en su suscripción. Por favor, verifique y asegúrese de que el NIT ingresado sea el correcto y esté asociado a su cuenta.',
//         ];

//         $infoValidation = [
//             'infoValidationZip' => false,
//             'errorMessages' => $errorMessages,
//         ];

//         $rip->validationZip = json_encode($infoValidation);
//         $rip->status = StatusRipsEnum::ERROR_NIT;
//         $rip->fail_nits = true;
//         $rip->save();
//     }

//     return $fail;
// }

function deleteFolderRecursively($folderPath)
{
    if (is_dir($folderPath)) {
        $files = glob($folderPath.'/*');
        foreach ($files as $file) {
            if (is_dir($file)) {
                deleteFolderRecursively($file);
            } else {
                unlink($file);
            }
        }
        rmdir($folderPath);

        return true;
    } else {
        return false;
    }
}

function removeKeysRecursively(&$array)
{
    foreach ($array as &$value) {
        if (is_array($value)) {
            removeKeysRecursively($value); // Recursive call to handle inner arrays
        }
    }
    $array = array_values($array); // Reindex the array
}
