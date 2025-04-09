<?php

use App\Helpers\Constants;
use App\Models\Cie10;
use App\Models\CupsRips;
use App\Models\GrupoServicio;
use App\Models\IpsCodHabilitacion;
use App\Models\IpsNoReps;
use App\Models\LstSiNo;
use App\Models\Municipio;
use App\Models\Pais;
use App\Models\Servicio;
use App\Models\TipoMedicamentoPosVersion2;
use App\Models\ZonaVersion2;
use Carbon\Carbon;

// 'validacion_type_Y' => 'N' todas la vidaciones que tengan este valor, seran informativas
// 31-01-2024 solicitud=> German

function RVC001($dataTxt, $value2, &$errorMessages)
{
    $validation = true;

    if ($dataTxt['numDocumentoIdObligado'] != $value2) {
        $errorMessages[] = [
            'validacion' => 'RVC001',
            'validacion_type_Y' => 'R',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'numDocumentoIdObligado',
            'data' => $dataTxt['numDocumentoIdObligado'],
            'error' => 'El NIT del facturador electrónico en salud informado en RIPS no coincide con el NIT informado en la factura electrónica de venta.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'R',
        'result' => $validation,
    ];
}
function RVC002($dataTxt, &$errorMessages)
{
    $validation = true;
    // dd($dataTxt['numDocumentoIdObligado']);
    $numDocumentoIdObligado = substr($dataTxt['numDocumentoIdObligado'], 0, 9); // Obtener los primeros 9 dígitos de valor pasado

    $ipsCodHabilitacion = IpsCodHabilitacion::where(function ($query) use ($numDocumentoIdObligado) {
        $query->where('nroIDPrestador', $numDocumentoIdObligado);
    })->first();

    $error = true;
    if ($ipsCodHabilitacion) {
        $error = false;
    }

    if ($error) {
        $ipsNoReps = IpsNoReps::where(function ($query) use ($dataTxt) {
            $query->where('nit', $dataTxt['numDocumentoIdObligado']);
        })->first();
        if ($ipsNoReps) {
            $error = false;
        }
    }

    if ($error) {

        $errorMessages[] = [
            'validacion' => 'RVC002',
            'validacion_type_Y' => 'R',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'numDocumentoIdObligado',
            'data' => $dataTxt['numDocumentoIdObligado'],
            'error' => 'El NIT informado en la factura electrónica de venta o en RIPS no se encuentra en la tabla “IPSCodHabilitación” para prestadores de servicios de salud o en “IPSnoREPS” para proveedores de tecnologías de salud o prestadores de regímenes especiales.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'R',
        'result' => $validation,
    ];
}
function RVC003($dataTxt, &$errorMessages)
{
    $validation = true;

    $error = false;

    $ipsNoReps = IpsNoReps::where(function ($query) use ($dataTxt) {
        $query->where('nit', $dataTxt['numDocumentoIdObligado']);
    })->first();

    if ($ipsNoReps) {
        foreach ($dataTxt['usuarios'] as $key => $value) {
            if (isset($value['servicios']['consultas']) && count($value['servicios']['consultas']) > 0) {
                $error = true;
            }
            if (isset($value['servicios']['procedimientos']) && count($value['servicios']['procedimientos']) > 0) {
                $error = true;
            }
            if (isset($value['servicios']['urgencias']) && count($value['servicios']['urgencias']) > 0) {
                $error = true;
            }
            if (isset($value['servicios']['hospitalizacion']) && count($value['servicios']['hospitalizacion']) > 0) {
                $error = true;
            }
            if (isset($value['servicios']['recienNacidos']) && count($value['servicios']['recienNacidos']) > 0) {
                $error = true;
            }
        }

        if ($error) {
            $errorMessages[] = [
                'validacion' => 'RVC003',
                'validacion_type_Y' => 'R',
                'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
                'file' => $dataTxt['file_name'] ?? null,
                'row' => $dataTxt['row'] ?? null,
                'column' => 'numDocumentoIdObligado',
                'data' => $dataTxt['numDocumentoIdObligado'],
                'error' => 'Por ser un proveedor de tecnologías de salud únicamente puede informar datos de usuarios, medicamentos y otros servicios',
            ];

            $validation = false;
        }
    }

    return [
        'validacion_type_Y' => 'R',
        'result' => $validation,
    ];
}

function RVC004($dataTxt, $value2, &$errorMessages)
{
    $validation = true;

    if ($dataTxt[Constants::KEY_NUMFACT] != $value2) {
        $errorMessages[] = [
            'validacion' => 'RVC004',
            'validacion_type_Y' => 'R',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => Constants::KEY_NUMFACT,
            'data' => $value2,
            'error' => 'El número de la factura informado en RIPS no coincide con el informado en la factura electrónica de venta.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'R',
        'result' => $validation,
    ];
}

function T03($value)
{
    // $value["tipoDocumentoIdentificacion"]
    return null;
}
function T04($value)
{
    if (empty($value)) {
        return 'Este valor no puede estar nulo.';
    }

    return null;
}

function U01($value)
{

    // $value["tipoDocumentoIdentificacion"]
    return null;
}
function U02($dataTxt, &$errorMessages)
{
    $validation = true;

    if (empty($dataTxt['numDocumentoIdentificacion'])) {
        $errorMessages[] = [
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'numDocumentoIdentificacion',
            'data' => $dataTxt['numDocumentoIdentificacion'],
            'error' => 'Este valor no puede estar nulo.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function RVC005($dataTxt, $value2, &$errorMessages)
{
    if ($dataTxt['tipoUsuario'] != $value2) {
        $errorMessages[] = [
            'validacion' => 'RVC005',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'tipoUsuario',
            'data' => $dataTxt['tipoUsuario'],
            'error' => 'El tipo de usuario no corresponde a un afiliado de la entidad responsable de pago informada en la factura electrónica de venta.',
        ];

        $validation = false;
    }

    $validation = true;

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function RVC006($dataTxt, &$errorMessages)
{
    $dataTxt['fechaNacimiento'] = substr($dataTxt['fechaNacimiento'], 0, 10);

    $validation = true;

    if (! empty($dataTxt['fechaNacimiento'])) {
        $fechaNacimiento = parseDate($dataTxt['fechaNacimiento']);
        $value2 = Carbon::now()->format('Y-m-d');
        // echo ($fechaNacimiento);
        if ($fechaNacimiento > $value2) {
            $errorMessages[] = [
                'validacion' => 'RVC006',
                'validacion_type_Y' => 'R',
                'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
                'file' => $dataTxt['file_name'] ?? null,
                'row' => $dataTxt['row'] ?? null,
                'column' => 'fechaNacimiento',
                'data' => $dataTxt['fechaNacimiento'],
                'error' => 'No es posible que la fecha de nacimiento sea mayor a la fecha de validación de los RIPS.',
            ];
            $validation = false;
        }
    } else {
        $errorMessages[] = [
            'validacion' => 'RVC006',
            'validacion_type_Y' => 'R',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'fechaNacimiento',
            'data' => $dataTxt['fechaNacimiento'],
            'error' => 'Este valor no puede estar nulo.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'R',
        'result' => $validation,
    ];
}
function RVC007($dataTxt, &$errorMessages, $dataExtra = null)
{
    $dataTxt['fechaNacimiento'] = substr($dataTxt['fechaNacimiento'], 0, 10);

    $validation = true;

    $edad = calcularEdad($dataTxt['fechaNacimiento']);
    $exito = false;
    if ($edad <= 6 && $dataTxt['tipoDocumentoIdentificacion'] == 'RC') {
        $exito = true;
    }

    $tiposPermitidos = ['NV', 'PA', 'CD', 'SC', 'PE', 'DE', 'MS'];
    if ($edad <= 3 && in_array($dataTxt['tipoDocumentoIdentificacion'], $tiposPermitidos)) {
        $exito = true;
    }

    $tiposPermitidos = ['TI', 'CE', 'PA', 'CD', 'SC', 'PE', 'DE', 'MS'];
    if ($edad >= 7 && $edad <= 17 && in_array($dataTxt['tipoDocumentoIdentificacion'], $tiposPermitidos)) {
        $exito = true;
    }

    $tiposPermitidos = ['CC'];
    if ($edad >= 18 && in_array($dataTxt['tipoDocumentoIdentificacion'], $tiposPermitidos)) {
        $exito = true;
    }

    $tiposPermitidos = ['TI', 'CE', 'PA', 'CD', 'SC', 'PE', 'DE', 'PT', 'AS'];
    if ($edad >= 18 && $edad <= 19 && in_array($dataTxt['tipoDocumentoIdentificacion'], $tiposPermitidos)) {
        $exito = true;
    }

    if (! $exito) {
        $errorMessages[] = [
            'validacion' => 'RVC007',
            'validacion_type_Y' => 'R',
            'num_invoice' => isset($dataExtra[Constants::KEY_NUMFACT]) ? $dataExtra[Constants::KEY_NUMFACT] : null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'fechaNacimiento',
            'data' => $dataTxt['tipoDocumentoIdentificacion'],
            'error' => 'El tipo de documento informado no es válido para la edad del usuario. Fecha de nacimiento: '.$dataTxt['fechaNacimiento'].', edad: '.$edad,
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'R',
        'result' => $validation,
    ];
}

function RVC008($dataTxt, &$errorMessages)
{
    $dataTxt['fechaNacimiento'] = substr($dataTxt['fechaNacimiento'], 0, 10);

    $validation = true;

    $edad = calcularEdad($dataTxt['fechaNacimiento']);
    // echo ($edad);
    if (isset($dataTxt['servicios']['recienNacidos']) && count($dataTxt['servicios']['recienNacidos']) > 0 && ($edad < 9 || $edad > 60)) {
        $errorMessages[] = [
            'validacion' => 'RVC008',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'fechaNacimiento',
            'data' => $dataTxt['fechaNacimiento'],
            'error' => 'Está informando datos de recién  nacido para un usuario menor a 9 años o mayor a 60 años.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function RVC009($dataTxt, &$errorMessages, $dataExtra = null)
{
    $validation = true;

    if (isset($dataTxt['servicios']['recienNacidos']) && count($dataTxt['servicios']['recienNacidos']) > 0 && $dataTxt['codSexo'] != 'F') {
        $errorMessages[] = [
            'validacion' => 'RVC009',
            'validacion_type_Y' => 'N',
            'num_invoice' => isset($dataExtra[Constants::KEY_NUMFACT]) ? $dataExtra[Constants::KEY_NUMFACT] : null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'codSexo',
            'data' => $dataTxt['codSexo'],
            'error' => 'Está informando datos de recién nacido para un paciente con sexo diferente a “Femenino”.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function RVC010($dataTxt, $value2, &$errorMessages)
{
    $validation = true;

    if ($dataTxt['codSexo'] == 3 && $value2 == 695101) {
        $errorMessages[] = [
            'validacion' => 'RVC010',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'codSexo',
            'data' => $dataTxt['codSexo'],
            'error' => 'Está informando que se le realizó una interrupción voluntaria del embarazo - IVE a un paciente con sexo diferente a “Femenino”.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}

function U06($dataTxt, &$errorMessages)
{
    $pais = Pais::where('codigo', $dataTxt['codPaisResidencia'])->first();
    if (! $pais) {
        $errorMessages[] = [
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'codPaisResidencia',
            'data' => $dataTxt['codPaisResidencia'],
            'error' => 'El código proporcionado no existe en la BD.',
        ];

        return false;
    }

    return true;
}
function U07($dataTxt, &$errorMessages)
{
    $pais = Municipio::where('codigo', $dataTxt['codMunicipioResidencia'])->first();
    if (! $pais) {
        $errorMessages[] = [
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'codMunicipioResidencia',
            'data' => $dataTxt['codMunicipioResidencia'],
            'error' => 'El código proporcionado no existe en la BD.',
        ];

        return false;
    }

    return true;
}
function U08($dataTxt, &$errorMessages)
{
    $pais = ZonaVersion2::where('codigo', $dataTxt['codZonaTerritorialResidencia'])->first();
    if (! $pais) {
        $errorMessages[] = [
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'codZonaTerritorialResidencia',
            'data' => $dataTxt['codZonaTerritorialResidencia'],
            'error' => 'El código proporcionado no existe en la BD.',
        ];

        return false;
    }

    return true;
}
function U09($dataTxt, &$errorMessages)
{
    $data = LstSiNo::where('codigo', $dataTxt['incapacidad'])->first();
    if (! $data) {
        $errorMessages[] = [
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'incapacidad',
            'data' => $dataTxt['incapacidad'],
            'error' => 'El código proporcionado no existe en la BD.',
        ];

        return false;
    }

    return true;
}
function U10($dataTxt, &$errorMessages)
{
    if (! $dataTxt['consecutivo']) {
        $errorMessages[] = [
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'consecutivo',
            'data' => $dataTxt['consecutivo'],
            'error' => 'Este valor no puede estar nulo.',
        ];

        return false;
    }

    return true;
}

function RVC011($dataTxt, $key, &$errorMessages)
{
    $validation = true;

    $ipsCodHabilitacion = IpsCodHabilitacion::where(function ($query) use ($dataTxt, $key) {
        $query->where('codigo', $dataTxt[$key]);
    })->first();

    $ipsNoReps = IpsNoReps::where(function ($query) use ($dataTxt, $key) {
        $query->where('codigo', $dataTxt[$key]);
    })->first();

    if (! $ipsCodHabilitacion && ! $ipsNoReps) {
        $errorMessages[] = [
            'validacion' => 'RVC011',
            'validacion_type_Y' => 'R',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => $key,
            'data' => $dataTxt[$key],
            'error' => 'El código del facturador electrónico en salud que otorga el Ministerio de Salud y Protección Social que fue informado en los RIPS no se encuentra registrado en SISPRO.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'R',
        'result' => $validation,
    ];
}
function RVC012($dataTxt, $key, &$errorMessages)
{
    $validation = true;

    if ($dataTxt[$key]) {
        $errorMessages[] = [
            'validacion' => 'RVC012',
            'validacion_type_Y' => 'R',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => $key,
            'data' => $dataTxt[$key],
            'error' => 'El código del facturador electrónico en salud que otorga el Ministerio de Salud y Protección Social que fue informado en los RIPS no se encuentra relacionado con el número de identificación tributaria - NIT informado en los RIPS.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'R',
        'result' => $validation,
    ];
}

function RVC013($dataTxt, $key, &$errorMessages)
{
    $dataTxt[$key] = substr($dataTxt[$key], 0, 10);

    $validation = true;

    if ($dataTxt[$key]) {
        $fechaInicioAtencion = parseDate($dataTxt[$key]);
        $now = Carbon::now()->format('Y-m-d');

        if ($fechaInicioAtencion > $now) {
            $errorMessages[] = [
                'validacion' => 'RVC013',
                'validacion_type_Y' => 'R',
                'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
                'file' => $dataTxt['file_name'] ?? null,
                'row' => $dataTxt['row'] ?? null,
                'column' => $key,
                'data' => $dataTxt[$key],
                'error' => 'La fecha y hora de la prestación del servicio es mayor a la fecha y hora actual.',
            ];

            $validation = false;
        }
    } else {
        $errorMessages[] = [
            'validacion' => 'RVC013',
            'validacion_type_Y' => 'R',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => $key,
            'data' => $dataTxt[$key],
            'error' => 'Este valor no puede estar nulo.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'R',
        'result' => $validation,
    ];
}
function RVC014($dataTxt, $key, $value2, &$errorMessages)
{
    $validation = true;

    if ($dataTxt[$key]) {
        $fechaInicioAtencion = parseDate($dataTxt[$key]);
        $value2 = Carbon::parse($value2)->format('Y-m-d');

        if ($fechaInicioAtencion > $value2) {
            $errorMessages[] = [
                'validacion' => 'RVC014',
                'validacion_type_Y' => 'R',
                'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
                'file' => $dataTxt['file_name'] ?? null,
                'row' => $dataTxt['row'] ?? null,
                'column' => $key,
                'data' => $dataTxt[$key],
                'error' => 'La fecha de la prestación del servicio se encuentra por fuera del periodo de facturación.',
            ];

            $validation = false;
        }
    } else {
        $errorMessages[] = [
            'validacion' => 'RVC014',
            'validacion_type_Y' => 'R',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => $key,
            'data' => $dataTxt[$key],
            'error' => 'Este valor no puede estar nulo.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function RVC079($dataTxt, $key, $value2, &$errorMessages)
{
    $validation = true;

    if ($dataTxt[$key] && $value2) {

        if ((strlen($dataTxt[$key]) === 10) && (strlen($value2) === 10)) {
            $fechaInicioAtencion = parseDate($dataTxt[$key]);
            $value2 = parseDate($value2);

            if ($fechaInicioAtencion > $value2) {
                $errorMessages[] = [
                    'validacion' => 'RVC079',
                    'validacion_type_Y' => 'R',
                    'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
                    'file' => $dataTxt['file_name'] ?? null,
                    'row' => $dataTxt['row'] ?? null,
                    'column' => $key,
                    'data' => $dataTxt[$key],
                    'error' => 'La fecha y hora de la prestación del servicio es menor a la fecha de nacimiento del usuario.',
                ];

                $validation = false;
            }
        }
    } else {
        $errorMessages[] = [
            'validacion' => 'RVC079',
            'validacion_type_Y' => 'R',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => $key,
            'data' => $dataTxt[$key],
            'error' => 'Este valor no puede estar nulo.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function C03($dataTxt, &$errorMessages)
{
    return true;
}

function RVC015($dataTxt, $key, $value2, &$errorMessages)
{
    $validation = true;

    $cupsRips = CupsRips::where(function ($query) use ($dataTxt, $key) {
        $query->where('codigo', $dataTxt[$key]);
    })->first();

    if (! $cupsRips || $cupsRips->extra_I != 'AC') {
        if ($dataTxt[$key]) {
            $errorMessages[] = [
                'validacion' => 'RVC015',
                'validacion_type_Y' => 'N',
                'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
                'file' => $dataTxt['file_name'] ?? null,
                'row' => $dataTxt['row'] ?? null,
                'column' => $key,
                'data' => $dataTxt[$key], // $dataTxt,
                'error' => 'El código CUPS informado no corresponde a una consulta.',
            ];

            $validation = false;
        }
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function RVC016($dataTxt, $key, $value2, &$errorMessages)
{
    $validation = true;

    $cupsRips = CupsRips::where(function ($query) use ($dataTxt, $key) {
        $query->where('codigo', $dataTxt[$key]);
    })->first();

    $error = false;
    if (! $cupsRips) {
        $error = true;
    } else {
        if ($cupsRips->extra_VI != 'Z' && $cupsRips->extra_VI != $value2['codSexo']) {
            $error = true;
        } else {
            $error = false;
        }
    }

    if ($error) {
        $errorMessages[] = [
            'validacion' => 'RVC016',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => $key,
            'data' => $dataTxt[$key],
            'error' => 'El código CUPS informado no corresponde a un CUPS para el sexo informado.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function RVC017($dataTxt, $key, $value2, &$errorMessages)
{
    $validation = true;

    if ($dataTxt[$key]) {
        $errorMessages[] = [
            'validacion' => 'RVC017',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => $key,
            'data' => $dataTxt[$key],
            'error' => 'El código CUPS informado no corresponde a  un CUPS para la cobertura o plan de beneficios informada en la factura electrónica de venta.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}

function RVC018($arrayGlobal, $dataTxt, $key, &$errorMessages)
{
    $dataTxt[$key] = substr($dataTxt[$key], 0, 10);

    $validation = true;

    $error = false;

    $dataRepetidas = collect($arrayGlobal)->groupBy($key)->filter(function ($items) {
        return $items->count() > 1; // Filtra solo los grupos que tienen más de un elemento
    });

    if (count($dataRepetidas) > 0) {
        foreach ($dataRepetidas as $consulta => $elementos) {
            $fechasRepetidas = $elementos->groupBy('fechaInicioAtencion')->filter(function ($items) {
                return $items->count() > 1; // Filtra solo las fechas que tienen más de un elemento
            });

            if ($fechasRepetidas->isNotEmpty()) {
                $error = true;
                break;
            }
        }
        if ($error) {
            $errorMessages[] = [
                'validacion' => 'RVC018',
                'validacion_type_Y' => 'N',
                'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
                'file' => $dataTxt['file_name'] ?? null,
                'row' => $dataTxt['row'] ?? null,
                'column' => $key,
                'data' => $dataTxt[$key],
                'error' => 'Tenga en cuenta que para el mismo paciente y en el mismo día no se puede informar más de una vez el código CUPS informado.',
            ];

            $validation = false;
        }
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function RVC019($dataTxt, $key, &$errorMessages)
{
    $validation = true;

    $cupsRips = CupsRips::where(function ($query) use ($dataTxt, $key) {
        $query->where('codigo', strval($dataTxt[$key]));
    })->first();

    $error = false;
    if (! $cupsRips) {
        $error = true;
    } elseif ($cupsRips->extra_V == 'S') {

        if (empty($dataTxt['codDiagnosticoPrincipal'])) {
            $error = true;
        } else {
            $cie10 = Cie10::where(function ($query) use ($dataTxt) {
                $query->where('codigo', strval($dataTxt['codDiagnosticoPrincipal']));
            })->first();

            if (! $cie10) {
                $error = true;
            } else {
                $error = false;
            }
        }
    }

    if ($error) {
        $errorMessages[] = [
            'validacion' => 'RVC019',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => $key,
            'data' => $dataTxt[$key],
            'error' => 'El código CUPS informado no corresponde a un código relacionado con el código de la Clasificación Internacional de Enfermedades - CIE.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function RVC027($dataTxt, $key, $value2, &$errorMessages)
{
    $validation = true;

    $cupsRips = CupsRips::where(function ($query) use ($dataTxt, $key) {
        $query->where('codigo', strval($dataTxt[$key]));
    })->first();

    $error = false;

    if (! $cupsRips) {
        $error = true;
    } elseif ($cupsRips->Interconsultas == 'S' && count($value2['servicios']['hospitalizacion']) == 0 && count($value2['servicios']['urgencias']) == 0) {
        $error = true;
    }

    if ($error) {
        $errorMessages[] = [
            'validacion' => 'RVC027',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => $key,
            'data' => $dataTxt[$key],
            'error' => 'El código CUPS no es de consulta intrahospitalaria o interconsulta y tiene informado datos de hospitalización o urgencias.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function RVC059($dataTxt, $key, $value2, &$errorMessages)
{
    $validation = true;

    if ($dataTxt[$key]) {
        $errorMessages[] = [
            'validacion' => 'RVC059',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => $key,
            'data' => $dataTxt[$key],
            'error' => 'El código CUPS informado no se encuentra relacionado  según  el  grupo  de  servicio, servicio, finalidad o causa.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function C06($dataTxt, &$errorMessages)
{
    $validation = true;

    $grupoServicios = GrupoServicio::where('codigo', $dataTxt['grupoServicios'])->first();

    if ($grupoServicios) {
        $errorMessages[] = [
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'grupoServicios',
            'data' => $dataTxt['grupoServicios'],
            'error' => 'No existe el código.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function C07($dataTxt, &$errorMessages)
{
    $codServicio = Servicio::where('codigo', $dataTxt['codServicio'])->first();

    if ($codServicio) {
        $errorMessages[] = [
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'codServicio',
            'data' => $dataTxt['codServicio'],
            'error' => 'No existe el código.',
        ];

        return false;
    }

    return true;
}
function RVC051($dataTxt, $value2, &$errorMessages)
{
    $validation = true;

    // RipsFinalidadConsultaVersion2::first();
    if ($dataTxt['finalidadTecnologiaSalud']) {
        $errorMessages[] = [
            'validacion' => 'RVC051',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'finalidadTecnologiaSalud',
            'data' => $dataTxt['finalidadTecnologiaSalud'],
            'error' => 'Tenga en cuenta que la finalidad informada no corresponde al sexo o la edad del paciente.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function RVC052($dataTxt, $value2, &$errorMessages)
{
    $validation = true;

    // RipsFinalidadConsultaVersion2::first();
    if ($dataTxt) {
        $errorMessages[] = [
            'validacion' => 'RVC052',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'codServicio',
            'data' => $dataTxt['codServicio'],
            'error' => 'Tenga en cuenta que la finalidad de interrupción voluntaria del embarazo - IVE tiene que tener relación con la causa que motiva la atención.',
        ];
        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function RVC067($dataTxt, $value2, &$errorMessages)
{
    $validation = true;

    // RipsFinalidadConsultaVersion2::first();
    if ($dataTxt) {
        $errorMessages[] = [
            'validacion' => 'RVC067',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'codServicio',
            'data' => $dataTxt['codServicio'],
            'error' => 'Tenga en cuenta que la finalidad informada no corresponde con la causa que motiva la atención.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function RVC068($dataTxt, $value2, &$errorMessages)
{
    $validation = true;

    // RipsFinalidadConsultaVersion2::first();
    if ($dataTxt) {
        $errorMessages[] = [
            'validacion' => 'RVC068',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'codServicio',
            'data' => $dataTxt['codServicio'],
            'error' => 'Tenga en cuenta que la finalidad informada no corresponde con la causa que motiva la atención.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function RVC069($dataTxt, $value2, &$errorMessages)
{
    $validation = true;

    // RipsFinalidadConsultaVersion2::first();
    if ($dataTxt) {
        $errorMessages[] = [
            'validacion' => 'RVC069',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'codServicio',
            'data' => $dataTxt['codServicio'],
            'error' => 'Tenga en cuenta que la finalidad informada no corresponde con la causa que motiva la atención.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function RVC070($dataTxt, $value2, &$errorMessages)
{
    $validation = true;

    // RipsFinalidadConsultaVersion2::first();
    if ($dataTxt) {
        $errorMessages[] = [
            'validacion' => 'RVC070',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'codServicio',
            'data' => $dataTxt['codServicio'],
            'error' => 'Tenga en cuenta que la finalidad informada no corresponde con la causa que motiva la atención.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function RVC071($dataTxt, $value2, &$errorMessages)
{
    $validation = true;

    // RipsFinalidadConsultaVersion2::first();
    if ($dataTxt) {
        $errorMessages[] = [
            'validacion' => 'RVC071',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'codServicio',
            'data' => $dataTxt['codServicio'],
            'error' => 'Tenga en cuenta que la finalidad informada no corresponde con la causa que motiva la atención.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function RVC072($dataTxt, $value2, &$errorMessages)
{
    $validation = true;

    // RipsFinalidadConsultaVersion2::first();
    if ($dataTxt) {
        $errorMessages[] = [
            'validacion' => 'RVC072',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'codServicio',
            'data' => $dataTxt['codServicio'],
            'error' => 'Tenga en cuenta que la finalidad informada no corresponde con la causa que motiva la atención.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function RVC073($dataTxt, $value2, &$errorMessages)
{
    $validation = true;

    // RipsFinalidadConsultaVersion2::first();
    if ($dataTxt) {
        $errorMessages[] = [
            'validacion' => 'RVC073',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'codServicio',
            'data' => $dataTxt['codServicio'],
            'error' => 'Tenga en cuenta que la finalidad informada no corresponde con la causa que motiva la atención.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function RVC074($dataTxt, $value2, &$errorMessages)
{
    $validation = true;

    // RipsFinalidadConsultaVersion2::first();
    if ($dataTxt) {
        $errorMessages[] = [
            'validacion' => 'RVC074',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'codServicio',
            'data' => $dataTxt['codServicio'],
            'error' => 'Tenga en cuenta que la finalidad informada no corresponde con la causa que motiva la atención.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function RVC075($dataTxt, $value2, &$errorMessages)
{
    $validation = true;

    // RipsFinalidadConsultaVersion2::first();
    if ($dataTxt) {
        $errorMessages[] = [
            'validacion' => 'RVC075',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'codServicio',
            'data' => $dataTxt['codServicio'],
            'error' => 'Tenga en cuenta que la finalidad informada no corresponde con la causa que motiva la atención.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function RVC084($dataTxt, $value2, &$errorMessages)
{
    $validation = true;

    // RipsFinalidadConsultaVersion2::first();
    if ($dataTxt) {
        $errorMessages[] = [
            'validacion' => 'RVC084',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'codServicio',
            'data' => $dataTxt['codServicio'],
            'error' => 'Para la finalidad informada no le aplican pagos moderadores.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function RVC085($dataTxt, $value2, &$errorMessages)
{
    $validation = true;

    // RipsFinalidadConsultaVersion2::first();
    if ($dataTxt) {
        $errorMessages[] = [
            'validacion' => 'RVC085',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'codServicio',
            'data' => $dataTxt['codServicio'],
            'error' => 'Valoración integral para la promoción  y  mantenimiento" no puede informar como diagnóstico un código CIE diferente a factores que influyen en el estado de salud y contacto con los servicios sanitarios (CIE10: Z00-Z99 o su equivalente en la CIE vigente).',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function C09($dataTxt, $value2, &$errorMessages)
{
    // RipsCausaExternaVersion2::first();
    if ($dataTxt) {
        $errorMessages[] = [
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'causaMotivoAtencion',
            'data' => $dataTxt['causaMotivoAtencion'],
            'error' => '.',
        ];

        return false;
    }

    return true;
}
function RVC028($dataTxt, $key, $value2, &$errorMessages)
{
    $validation = true;

    $cie10 = Cie10::where(function ($query) use ($dataTxt, $key) {
        $query->where('codigo', strval($dataTxt[$key]));
    })->first();

    $error = false;
    if (! $cie10) {
        $errorMessages[] = [
            'validacion' => 'RVC028',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => $key,
            'data' => $dataTxt[$key],
            'error' => 'No se evidencia el diagnostico del paciente.',
        ];
        $validation = false;
    } else {
        if ($cie10->extra_X == 'A' || ($cie10->extra_X == $value2['codSexo'])) {
            $error = false;
        } else {
            $error = truse;
        }
    }

    if ($error) {
        $errorMessages[] = [
            'validacion' => 'RVC028',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => $key,
            'data' => $dataTxt[$key],
            'error' => 'El código CIE no corresponde al sexo a la edad del paciente.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function RVC029($dataTxt, $key, &$errorMessages)
{
    $validation = true;

    // Cie10::first();
    if ($dataTxt) {
        $errorMessages[] = [
            'validacion' => 'RVC029',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => $key,
            'data' => $dataTxt[$key],
            'error' => 'El código CIE no se encuentra relacionado con el código CUPS de la consulta.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function RVC031($dataTxt, $key, &$errorMessages)
{
    $validation = true;

    // Cie10::first();
    if ($dataTxt[$key]) {
        $errorMessages[] = [
            'validacion' => 'RVC031',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => $key,
            'data' => $dataTxt[$key],
            'error' => 'El código CIE informado no puede ser de causas externas de morbilidad y de mortalidad (CIE10: V01-Y98 o su equivalente en la CIE vigente).',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function RVC086($dataTxt, $key1, &$errorMessages)
{
    $validation = true;

    if ($dataTxt[$key1] == $dataTxt['codDiagnosticoPrincipal']) {
        $errorMessages[] = [
            'validacion' => 'RVC086',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => $key1,
            'data' => $dataTxt[$key1],
            'error' => 'El código de diagnóstico relacionado es igual al código de diagnóstico principal.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function RVC087($dataTxt, $key1, &$errorMessages)
{
    $validation = true;

    if ($dataTxt[$key1]) {
        $errorMessages[] = [
            'validacion' => 'RVC087',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => $key1,
            'data' => $dataTxt[$key1],
            'error' => 'El código de diagnóstico relacionado es igual a otro código de diagnóstico relacionado.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function C14($dataTxt, &$errorMessages)
{
    if ($dataTxt['tipoDiagnosticoPrincipal']) {
        $errorMessages[] = [
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'tipoDiagnosticoPrincipal',
            'data' => $dataTxt['tipoDiagnosticoPrincipal'],
            'error' => '.',
        ];

        return false;
    }

    return true;
}
function C15($dataTxt, &$errorMessages)
{
    if ($dataTxt['tipoDocumentoIdentificacion']) {
        $errorMessages[] = [
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'tipoDocumentoIdentificacion',
            'data' => $dataTxt['tipoDocumentoIdentificacion'],
            'error' => '.',
        ];

        return false;
    }

    return true;
}
function C16($dataTxt, &$errorMessages)
{
    if ($dataTxt['numDocumentoIdentificacion']) {
        $errorMessages[] = [
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'numDocumentoIdentificacion',
            'data' => $dataTxt['numDocumentoIdentificacion'],
            'error' => '.',
        ];

        return false;
    }

    return true;
}
function RVC034($dataTxt, $key, &$errorMessages)
{
    $validation = true;

    if ($dataTxt[$key]) {
        $errorMessages[] = [
            'validacion' => 'RVC034',
            'validacion_type_Y' => 'R',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => $key,
            'data' => $dataTxt[$key],
            'error' => 'Debe ingresar un valor mayor a cero porque la modalidad de pago corresponde a pago por evento.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'R',
        'result' => $validation,
    ];
}
function RVC035($dataTxt, $key, &$errorMessages)
{
    $validation = true;

    if ($dataTxt[$key]) {
        $errorMessages[] = [
            'validacion' => 'RVC035',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => $key,
            'data' => $dataTxt[$key],
            'error' => 'El cobro de la cuota moderadora solamente aplica a pacientes del régimen contributivo.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'R',
        'result' => $validation,
    ];
}
function RVC037($dataTxt, $key, &$errorMessages)
{
    $validation = true;

    if ($dataTxt[$key]) {
        $errorMessages[] = [
            'validacion' => 'RVC037',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => $key,
            'data' => $dataTxt[$key],
            'error' => 'El cobro pagos moderadores de planes voluntarios no se realiza a pacientes del régimen contributivo.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function RVC036($dataTxt, $key, &$errorMessages)
{
    $validation = true;

    if ($dataTxt[$key]) {
        $errorMessages[] = [
            'validacion' => 'RVC036',
            'validacion_type_Y' => 'R',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => $key,
            'data' => $dataTxt[$key],
            'error' => 'El valor del pago moderador informado en la factura electrónica de venta corresponde a la sumatoria de detalles de valores de pagos moderadores informados en RIPS.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'R',
        'result' => $validation,
    ];
}
function RVC060($dataTxt, &$errorMessages)
{
    $validation = true;

    if ($dataTxt['valorPagoModerador']) {
        $errorMessages[] = [
            'validacion' => 'RVC060',
            'validacion_type_Y' => 'R',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'valorPagoModerador',
            'data' => $dataTxt['valorPagoModerador'],
            'error' => 'El valor del pago moderador no es correcto, debe informar un valor numérico mayor o igual a 1.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'R',
        'result' => $validation,
    ];
}
function RVC061($dataTxt, &$errorMessages)
{
    $validation = true;

    if ($dataTxt['valorPagoModerador']) {
        $errorMessages[] = [
            'validacion' => 'RVC061',
            'validacion_type_Y' => 'R',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'valorPagoModerador',
            'data' => $dataTxt['valorPagoModerador'],
            'error' => 'No es posible informar el valor de un pago moderador si el pago moderador no aplica.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'R',
        'result' => $validation,
    ];
}
function C20($dataTxt, &$errorMessages)
{
    if ($dataTxt['numFEVPagoModerador']) {
        $errorMessages[] = [
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'numFEVPagoModerador',
            'data' => $dataTxt['numFEVPagoModerador'],
            'error' => '.',
        ];

        return false;
    }

    return true;
}
function C21($dataTxt, &$errorMessages)
{
    if ($dataTxt['consecutivo']) {
        $errorMessages[] = [
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'consecutivo',
            'data' => $dataTxt['consecutivo'],
            'error' => '.',
        ];

        return false;
    }

    return true;
}

function P03($dataTxt, &$errorMessages)
{
    return true;
}

function RVC048($dataTxt, &$errorMessages)
{
    $validation = true;

    if ($dataTxt['numAutorizacion']) {
        $errorMessages[] = [
            'validacion' => 'RVC048',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'numAutorizacion',
            'data' => $dataTxt['numAutorizacion'],
            'error' => 'El servicio o tecnología de salud es financiado con presupuesto máximo y el número de autorización no corresponde al número de prescripción en MIPRES.',
        ];
        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function RVC020($dataTxt, &$errorMessages)
{
    $validation = true;

    $cupsRips = CupsRips::where(function ($query) use ($dataTxt) {
        $query->where('codigo', strval($dataTxt['codProcedimiento']));
    })->first();

    if (! $cupsRips || $cupsRips->extra_I != 'AP') {
        $errorMessages[] = [
            'validacion' => 'RVC020',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'codProcedimiento',
            'data' => $dataTxt['codProcedimiento'],
            'error' => 'El código CUPS informado no corresponde a un procedimiento.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function RVC021($dataTxt, $value2, &$errorMessages)
{
    $validation = true;

    $cupsRips = CupsRips::where(function ($query) use ($dataTxt) {
        $query->where('codigo', strval($dataTxt['codProcedimiento']));
    })->first();

    $error = false;

    if (! $cupsRips) {
        $error = true;
    } elseif ($cupsRips->extra_VIII == 'E' && count($value2['servicios']['hospitalizacion']) > 0) {
        $error = false;
    } else {
        $error = true;
    }

    if ($cupsRips && $cupsRips->extra_VIII == 'Z' || empty($cupsRips->extra_VIII)) {
        $error = false;
    }

    if ($error) {
        $errorMessages[] = [
            'validacion' => 'RVC021',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'codProcedimiento',
            'data' => $dataTxt['codProcedimiento'],
            'error' => 'Tenga en cuenta que para el código CUPS informado se requiere de un tiempo de estancia del paciente.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function RVC022($dataTxt, &$errorMessages)
{
    $validation = true;

    if ($dataTxt['codProcedimiento']) {
        $errorMessages[] = [
            'validacion' => 'RVC022',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'codProcedimiento',
            'data' => $dataTxt['codProcedimiento'],
            'error' => 'Tenga en cuenta que, si el código CUPS corresponde a un procedimiento de parto, debe informar en los RIPS datos de hospitalización.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function RVC023($dataTxt, &$errorMessages)
{
    $validation = true;

    if ($dataTxt['codProcedimiento']) {
        $errorMessages[] = [
            'validacion' => 'RVC023',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'codProcedimiento',
            'data' => $dataTxt['codProcedimiento'],
            'error' => 'Tenga en cuenta que, si el código CUPS corresponde a un procedimiento de parto, debe informar en los RIPS datos del recién nacido.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function RVC024($dataTxt, &$errorMessages)
{
    $validation = true;

    $cupsRips = CupsRips::where(function ($query) use ($dataTxt) {
        $query->where('codigo', $dataTxt['codTecnologiaSalud']);
    })->first();

    if (! $cupsRips) {
        $errorMessages[] = [
            'validacion' => 'RVC024',
            'validacion_type_Y' => 'R',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'codTecnologiaSalud',
            'data' => $dataTxt['codTecnologiaSalud'],
            'error' => 'El código informado del traslado, transporte o estancia no corresponde a un código CUPS.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'R',
        'result' => $validation,
    ];
}
function RVC025($dataTxt, &$errorMessages)
{
    $validation = true;

    if ($dataTxt['codTecnologiaSalud']) {
        $errorMessages[] = [
            'validacion' => 'RVC025',
            'validacion_type_Y' => 'R',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'codTecnologiaSalud',
            'data' => $dataTxt['codTecnologiaSalud'],
            'error' => 'El código de servicios complementarios no corresponde a un código dado en la tabla de referencia de MIPRES.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'R',
        'result' => $validation,
    ];
}
function RVC026($dataTxt, &$errorMessages)
{
    $validation = true;

    $cupsRips = CupsRips::where(function ($query) use ($dataTxt) {
        $query->where('codigo', strval($dataTxt['codTecnologiaSalud']));
    })->first();

    $error = false;

    if (! $cupsRips) {
        $error = true;
    } else {
        if ($cupsRips->extra_I == 'AT') {
            $error = false;
        }
        if (in_array($cupsRips->extra_I, ['AP', 'AC'])) {
            $error = true;
        }
    }

    if ($error) {
        $errorMessages[] = [
            'validacion' => 'RVC026',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'codTecnologiaSalud',
            'data' => $dataTxt['codTecnologiaSalud'],
            'error' => 'El código CUPS informado no corresponde a una cobertura de otros servicios.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function RVC090($dataTxt, &$errorMessages)
{
    $validation = true;

    if ($dataTxt['codProcedimiento']) {
        $errorMessages[] = [
            'validacion' => 'RVC090',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'codProcedimiento',
            'data' => $dataTxt['codProcedimiento'],
            'error' => 'Tenga en cuenta que, si el código CUPS corresponde a un procedimiento quirúrgico, debe informar en los RIPS los datos de la sala usada.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function P06($dataTxt, &$errorMessages)
{
    $validation = true;

    if ($dataTxt) {
        $errorMessages[] = [
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => '??',
            'data' => '??',
            'error' => '??.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function P07($dataTxt, &$errorMessages)
{
    if ($dataTxt['grupoServicios']) {
        $errorMessages[] = [
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'grupoServicios',
            'data' => $dataTxt['grupoServicios'],
            'error' => '??.',
        ];

        return false;
    }

    return true;
}
function P08($dataTxt, &$errorMessages)
{
    if ($dataTxt['grupoServicios']) {
        $errorMessages[] = [
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'grupoServicios',
            'data' => $dataTxt['grupoServicios'],
            'error' => '??.',
        ];

        return false;
    }

    return true;
}
function P09($dataTxt, &$errorMessages)
{
    if ($dataTxt['codServicio']) {
        $errorMessages[] = [
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'codServicio',
            'data' => $dataTxt['codServicio'],
            'error' => '??.',
        ];

        return false;
    }

    return true;
}
function RVC083($dataTxt, &$errorMessages)
{
    $validation = true;

    if ($dataTxt['finalidadTecnologiaSalud']) {
        $errorMessages[] = [
            'validacion' => 'RVC083',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'finalidadTecnologiaSalud',
            'data' => $dataTxt['finalidadTecnologiaSalud'],
            'error' => 'El código CUPS informado no corresponde a una intervención colectiva.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function P11($dataTxt, &$errorMessages)
{
    if ($dataTxt['tipoDocumentoIdentificacion']) {
        $errorMessages[] = [
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'tipoDocumentoIdentificacion',
            'data' => $dataTxt['tipoDocumentoIdentificacion'],
            'error' => '??.',
        ];

        return false;
    }

    return true;
}
function P12($dataTxt, &$errorMessages)
{
    if ($dataTxt['numDocumentoIdentificacion']) {
        $errorMessages[] = [
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'numDocumentoIdentificacion',
            'data' => $dataTxt['numDocumentoIdentificacion'],
            'error' => '??.',
        ];

        return false;
    }

    return true;
}
function RVC030($dataTxt, $key, &$errorMessages)
{
    $validation = true;

    if ($dataTxt[$key]) {
        $errorMessages[] = [
            'validacion' => 'RVC030',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => $key,
            'data' => $dataTxt[$key],
            'error' => 'El código CIE no se encuentra relacionado con el código CUPS del procedimiento.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function RVC032($dataTxt, $key, &$errorMessages)
{
    $validation = true;

    if ($dataTxt[$key]) {
        $errorMessages[] = [
            'validacion' => 'RVC032',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => $key,
            'data' => $dataTxt[$key],
            'error' => 'El código CIE informado no puede ser de síntomas, signos y resultados anormales de pruebas complementarias, no clasificados bajo otro concepto (CIE10: R00-R99 o su equivalente en la CIE vigente).',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function RVC033($dataTxt, &$errorMessages)
{
    $validation = true;

    if ($dataTxt['codDiagnosticoPrincipal']) {
        $errorMessages[] = [
            'validacion' => 'RVC033',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'codDiagnosticoPrincipal',
            'data' => $dataTxt['codDiagnosticoPrincipal'],
            'error' => 'Tener en cuenta que el código de CIE no puede ser de factores que influyen en el estado de salud y contacto con los servicios sanitarios (CIE10: Z00-Z99 o su equivalente en la CIE vigente).',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}

function P19($dataTxt, &$errorMessages)
{
    if ($dataTxt['numFEVPagoModerador']) {
        $errorMessages[] = [
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'numFEVPagoModerador',
            'data' => $dataTxt['numFEVPagoModerador'],
            'error' => '.',
        ];

        return false;
    }

    return true;
}
function P20($dataTxt, &$errorMessages)
{
    if ($dataTxt['consecutivo']) {
        $errorMessages[] = [
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'consecutivo',
            'data' => $dataTxt['consecutivo'],
            'error' => '.',
        ];

        return false;
    }

    return true;
}
function RVC049($dataTxt, &$errorMessages)
{
    $validation = true;

    if ($dataTxt['idMIPRES']) {
        $errorMessages[] = [
            'validacion' => 'RVC049',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'idMIPRES',
            'data' => $dataTxt['idMIPRES'],
            'error' => 'El número de "ID de entrega" no corresponde al registrado en MIPRES.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function RVC055($dataTxt, &$errorMessages)
{
    $validation = true;

    $data = TipoMedicamentoPosVersion2::where('codigo', $dataTxt['tipoMedicamento'])->first();
    if ($data) {
        $errorMessages[] = [
            'validacion' => 'RVC055',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'tipoMedicamento',
            'data' => $dataTxt['tipoMedicamento'],
            'error' => 'Tenga en cuenta que está informando un medicamento UNIRS que no se encuentra en el listado de UNIRS autorizado por la entidad competente.',
        ];
        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function RVC063($dataTxt, &$errorMessages)
{
    $validation = true;

    $ium = null; // Ium::where("codigo",$dataTxt)->first();
    $catalogoCum = null; // CatalogoCum::where("codigo",$dataTxt)->first();
    if ($ium || $catalogoCum) {
        $errorMessages[] = [
            'validacion' => 'RVC063',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => '??',
            'data' => '??',
            'error' => 'El código  IUM o CUM ingresado no se encuentra en el catálogo de datos de IUM o CUM, respectivamente.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function RVC064($dataTxt, &$errorMessages)
{
    $validation = true;

    if ($dataTxt) {
        $errorMessages[] = [
            'validacion' => 'RVC064',
            'validacion_type_Y' => 'R',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => '??',
            'data' => '??',
            'error' => 'Informó un código de tecnología de salud para una preparación magistral y este tipo de tecnología de salud actualmente no tiene codificación.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'R',
        'result' => $validation,
    ];
}
function RVC065($dataTxt, $key, &$errorMessages)
{
    $validation = true;

    if ($dataTxt[$key]) {
        $errorMessages[] = [
            'validacion' => 'RVC065',
            'validacion_type_Y' => 'N',
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => $key,
            'data' => $dataTxt[$key],
            'error' => 'Tenga en cuenta que no es necesario que informe dato para el tipo de medicamento informado ya que el Ministerio de Salud y Protección Social obtiene los datos del medicamento informado a partir del código informado.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function M13($dataTxt, &$errorMessages)
{
    if ($dataTxt['unidadMinDispensa']) {
        $errorMessages[] = [
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'unidadMinDispensa',
            'data' => $dataTxt['unidadMinDispensa'],
            'error' => '??.',
        ];

        return false;
    }

    return true;
}
function M14($dataTxt, &$errorMessages)
{
    if ($dataTxt['cantidadMedicamento']) {
        $errorMessages[] = [
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'cantidadMedicamento',
            'data' => $dataTxt['cantidadMedicamento'],
            'error' => '??.',
        ];

        return false;
    }

    return true;
}
function M15($dataTxt, &$errorMessages)
{
    if ($dataTxt['diasTratamiento']) {
        $errorMessages[] = [
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'diasTratamiento',
            'data' => $dataTxt['diasTratamiento'],
            'error' => '??.',
        ];

        return false;
    }

    return true;
}
function M16($dataTxt, $value2, &$errorMessages)
{
    if ($dataTxt['tipoDocumentoIdentificacion'] != $value2) {
        $errorMessages[] = [
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'tipoDocumentoIdentificacion',
            'data' => $dataTxt['tipoDocumentoIdentificacion'],
            'error' => '??.',
        ];

        return false;
    }

    return true;
}
function M17($dataTxt, $value2, &$errorMessages)
{
    if ($dataTxt['numDocumentoIdentificacion'] != $value2) {
        $errorMessages[] = [
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'numDocumentoIdentificacion',
            'data' => $dataTxt['numDocumentoIdentificacion'],
            'error' => '??.',
        ];

        return false;
    }

    return true;
}
function M19($dataTxt, &$errorMessages)
{
    if ($dataTxt['vrServicio']) {
        $errorMessages[] = [
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'vrServicio',
            'data' => $dataTxt['vrServicio'],
            'error' => '??.',
        ];

        return false;
    }

    return true;
}
function M22($dataTxt, &$errorMessages)
{
    if ($dataTxt['numFEVPagoModerador']) {
        $errorMessages[] = [
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'numFEVPagoModerador',
            'data' => $dataTxt['numFEVPagoModerador'],
            'error' => '??.',
        ];

        return false;
    }

    return true;
}
function M23($dataTxt, &$errorMessages)
{
    if ($dataTxt['consecutivo']) {
        $errorMessages[] = [
            'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => 'consecutivo',
            'data' => $dataTxt['consecutivo'],
            'error' => '??.',
        ];

        return false;
    }

    return true;
}
function RVC038($dataTxt, $key, &$errorMessages)
{
    $dataTxt[$key] = substr($dataTxt[$key], 0, 10);

    $validation = true;

    if ($dataTxt[$key]) {
        $fecha = parseDate($dataTxt[$key]);
        $value2 = Carbon::now()->format('Y-m-d');
        if ($fecha > $value2) {
            $errorMessages[] = [
                'validacion' => 'RVC038',
                'validacion_type_Y' => 'R',
                'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
                'file' => $dataTxt['file_name'] ?? null,
                'row' => $dataTxt['row'] ?? null,
                'column' => $key,
                'data' => $dataTxt[$key],
                'error' => 'La fecha y hora de ingreso es mayor a la fecha y hora actual.',
            ];

            $validation = false;
        }
    }

    return [
        'validacion_type_Y' => 'R',
        'result' => $validation,
    ];
}
function RVC039($dataTxt, $key, $key2, &$errorMessages)
{
    $dataTxt[$key] = substr($dataTxt[$key], 0, 10);

    $validation = true;

    if ($dataTxt[$key]) {
        $fecha = parseDate($dataTxt[$key]);
        $fecha2 = parseDate($dataTxt[$key2]);
        if ($fecha > $fecha2) {
            $errorMessages[] = [
                'validacion' => 'RVC039',
                'validacion_type_Y' => 'R',
                'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
                'file' => $dataTxt['file_name'] ?? null,
                'row' => $dataTxt['row'] ?? null,
                'column' => $key,
                'data' => $dataTxt[$key],
                'error' => 'La fecha y hora de ingreso es mayor a la fecha y hora de egreso.',
            ];

            $validation = false;
        }
    }

    return [
        'validacion_type_Y' => 'R',
        'result' => $validation,
    ];
}
function RVC040($dataTxt, $key, $key2, &$errorMessages)
{
    $dataTxt[$key] = substr($dataTxt[$key], 0, 10);

    $validation = true;

    if ($dataTxt[$key]) {
        $fecha = parseDate($dataTxt[$key]);
        $fecha2 = parseDate($dataTxt[$key2]);
        $diferenciaEnHoras = $fecha->diffInHours($fecha2);
        if ($diferenciaEnHoras > 48) {
            $errorMessages[] = [
                'validacion' => 'RVC040',
                'validacion_type_Y' => 'N',
                'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
                'file' => $dataTxt['file_name'] ?? null,
                'row' => $dataTxt['row'] ?? null,
                'column' => $key,
                'data' => $dataTxt[$key],
                'error' => 'Tenga en cuenta que está informando una estancia en urgencias superior a 48 horas.',
            ];

            $validation = false;
        }
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function RVC041($dataTxt, $key, $key2, &$errorMessages)
{
    $validation = true;

    if ($dataTxt[$key]) {
        $fecha = parseDate($dataTxt[$key]);
        $fecha2 = parseDate($dataTxt[$key]);
        $diferenciaEnHoras = $fecha->diffInHours($fecha2);
        if ($diferenciaEnHoras <= 6) {
            $errorMessages[] = [
                'validacion' => 'RVC041',
                'validacion_type_Y' => 'N',
                'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
                'file' => $dataTxt['file_name'] ?? null,
                'row' => $dataTxt['row'] ?? null,
                'column' => $key,
                'data' => $dataTxt[$key],
                'error' => 'Tenga en cuenta que está informando una estancia en hospitalización menor a 6 horas.',
            ];

            $validation = false;
        }
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}
function RVC042($dataTxt, $key, &$errorMessages)
{
    $validation = true;

    if ($dataTxt[$key] == 2) {
        if (empty($dataTxt['codDiagnosticoCausaMuerte'])) {
            $errorMessages[] = [
                'validacion' => 'RVC042',
                'validacion_type_Y' => 'R',
                'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
                'file' => $dataTxt['file_name'] ?? null,
                'row' => $dataTxt['row'] ?? null,
                'column' => $key,
                'data' => $dataTxt[$key],
                'error' => 'No informó la causa básica de muerte la cual es necesaria ya que la condición y destino del usuario al egreso fue ""paciente muerte"".',
            ];

            $validation = false;
        }
    }

    return [
        'validacion_type_Y' => 'R',
        'result' => $validation,
    ];
}
function RVC043($dataTxt, $key, &$errorMessages)
{
    $dataTxt[$key] = substr($dataTxt[$key], 0, 10);

    $validation = true;

    if ($dataTxt[$key]) {
        $fecha = parseDate($dataTxt[$key]);
        $fecha2 = Carbon::now()->format('Y-m-d');
        if ($fecha > $fecha2) {
            $errorMessages[] = [
                'validacion' => 'RVC043',
                'validacion_type_Y' => 'R',
                'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
                'file' => $dataTxt['file_name'] ?? null,
                'row' => $dataTxt['row'] ?? null,
                'column' => $key,
                'data' => $dataTxt[$key],
                'error' => 'La fecha y hora de egreso es mayor a la fecha y hora actual.',
            ];

            $validation = false;
        }
    }

    return [
        'validacion_type_Y' => 'R',
        'result' => $validation,
    ];
}

function RVC044($dataTxt, $key, &$errorMessages)
{
    $dataTxt[$key] = substr($dataTxt[$key], 0, 10);

    $validation = true;

    if ($dataTxt[$key]) {
        $fecha = parseDate($dataTxt[$key]);
        $fecha2 = Carbon::now()->format('Y-m-d');
        if ($fecha > $fecha2) {
            $errorMessages[] = [
                'validacion' => 'RVC044',
                'validacion_type_Y' => 'R',
                'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
                'file' => $dataTxt['file_name'] ?? null,
                'row' => $dataTxt['row'] ?? null,
                'column' => $key,
                'data' => $dataTxt[$key],
                'error' => 'La fecha de egreso se encuentra por fuera del periodo de facturación',
            ];

            $validation = false;
        }
    }

    return [
        'validacion_type_Y' => 'R',
        'result' => $validation,
    ];
}
function RVC046($dataTxt, $key, $key2, &$errorMessages)
{
    $dataTxt[$key] = substr($dataTxt[$key], 0, 10);
    $dataTxt[$key2] = substr($dataTxt[$key2], 0, 10);

    $validation = true;

    if ($dataTxt[$key]) {
        $fecha = parseDate($dataTxt[$key]);
        $fecha2 = parseDate($dataTxt[$key2]);
        if ($fecha < $fecha2) {
            $errorMessages[] = [
                'validacion' => 'RVC046',
                'validacion_type_Y' => 'R',
                'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
                'file' => $dataTxt['file_name'] ?? null,
                'row' => $dataTxt['row'] ?? null,
                'column' => $key,
                'data' => $dataTxt[$key],
                'error' => 'La fecha y hora de egreso es mayor a la fecha y hora de nacimiento del recién nacido.',
            ];

            $validation = false;
        }
    }

    return [
        'validacion_type_Y' => 'R',
        'result' => $validation,
    ];
}
function RVC053($dataTxt, $key, $key2, $key3, &$errorMessages)
{
    $validation = true;

    if ($dataTxt[$key]) {
        $fecha = parseDate($dataTxt[$key2]);
        $fecha2 = parseDate($dataTxt[$key3]);
        if ($fecha < $fecha2) {
            $errorMessages[] = [
                'validacion' => 'RVC053',
                'validacion_type_Y' => 'N',
                'num_invoice' => $dataTxt[Constants::KEY_NUMFACT] ?? $dataTxt['numFEVPagoModerador'] ?? null,
                'file' => $dataTxt['file_name'] ?? null,
                'row' => $dataTxt['row'] ?? null,
                'column' => $key,
                'data' => $dataTxt[$key],
                'error' => 'Tenga en cuenta que la condición del paciente es "Paciente muerto" y está informando servicios con fecha y hora posterior a la muerte.',
            ];

            $validation = false;
        }
    }

    return [
        'validacion_type_Y' => 'N',
        'result' => $validation,
    ];
}

function validationFormatDate($dataTxt, $key, &$errorMessages, $dataExtra = null, $validacion_type_Y = 'R', $typeFormat = 1)
{
    switch ($typeFormat) {
        case 1:
            $format = '/^([0-9]{4})-([0-1]{1}[0-9]{1})-([0-3]{1}[0-9]{1}) ([0-2]{1}[0-9]{1}):([0-5]{1}[0-9]{1})$/';
            $msg1 = 'Año-Mes-Día Hora-Minuto';
            $msg2 = 'AAAA-MM-DD hh:mm';
            break;
        case 2:
            $format = '/^([0-9]{4})-([0-1]{1}[0-9]{1})-([0-3]{1}[0-9]{1})$/';
            $msg1 = 'Año-Mes-Día';
            $msg2 = 'AAAA-MM-DD';
            break;
    }

    $dataTxt[$key] = trim($dataTxt[$key]);
    $error = true;
    if (! empty($dataTxt[$key])) {
        $error = false;
        $preg = preg_match($format, $dataTxt[$key]);
        if (! $preg) {
            $error = true;
        }
    }

    if ($error) {
        $errorMessages[] = [
            'validacion' => 'validationFormatDate',
            'validacion_type_Y' => $validacion_type_Y,
            'num_invoice' => isset($dataExtra[Constants::KEY_NUMFACT]) ? $dataExtra[Constants::KEY_NUMFACT] : null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => $key,
            'data' => $dataTxt[$key],
            'error' => "El campo $key debe estar en el formato $msg1 ($msg2).",
        ];

        return false;
    }

    return true;
}

function onlyNumbers($dataTxt, $key, &$errorMessages, $dataExtra = null, $validacion_type_Y = 'R')
{

    if (! is_numeric($dataTxt[$key])) {
        $errorMessages[] = [
            'validacion' => 'onlyNumbers',
            'validacion_type_Y' => $validacion_type_Y,
            'num_invoice' => isset($dataExtra[Constants::KEY_NUMFACT]) ? $dataExtra[Constants::KEY_NUMFACT] : null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => $key,
            'data' => $dataTxt[$key],
            'error' => "El campo $key solo debe incluir números. No se permiten letras ni caracteres especiales como .,/-.",
        ];

        return false;
    }

    return true;
}

function validateYesNo($dataTxt, $key, &$errorMessages, $dataExtra = null, $validacion_type_Y = 'R')
{
    // Convertir a minúsculas para hacer la comparación insensible a mayúsculas
    $valor = strtolower($dataTxt[$key]);

    // Verificar si el valor NO es "si" ni "no"
    if ($valor !== 'si' && $valor !== 'no') {

        $errorMessages[] = [
            'validacion' => 'validateYesNo',
            'validacion_type_Y' => $validacion_type_Y,
            'num_invoice' => isset($dataExtra[Constants::KEY_NUMFACT]) ? $dataExtra[Constants::KEY_NUMFACT] : null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => $key,
            'data' => $dataTxt[$key],
            'error' => "El campo $key únicamente puede contener 'Si' o 'No'.",
        ];

        return false;
    }

    return true;
}

function searchInArray($dataTxt, $key, &$errorMessages, $dataExtra = null, $validacion_type_Y = 'R', $stringArray = [])
{
    $arrayPersonalized = [];
    $msg = 'Asegúrese de incluir el cero inicial';
    switch ($key) {
        case 'TipoNota':
            $arrayPersonalized = ['00', '01', '02', '03'];
            break;
        case 'codZonaTerritorialResidencia':
            $arrayPersonalized = ['01', '02'];
            break;
        case 'modalidadGrupoServicioTecSal':
            $arrayPersonalized = ['01', '02', '03', '04', '06', '07', '08', '09'];
            break;
        case 'grupoServicios':
            $arrayPersonalized = ['01', '02', '03', '04', '05'];
            break;
        case 'codServicio':
            $arrayPersonalized = [
                '105',
                '106',
                '107',
                '108',
                '109',
                '110',
                '159',
                '1101',
                '1102',
                '1103',
                '1104',
                '1105',
                '120',
                '129',
                '130',
                '131',
                '132',
                '133',
                '134',
                '135',
                '138',
                '201',
                '202',
                '203',
                '204',
                '205',
                '207',
                '208',
                '209',
                '210',
                '211',
                '212',
                '213',
                '214',
                '215',
                '217',
                '218',
                '227',
                '231',
                '232',
                '233',
                '234',
                '235',
                '237',
                '245',
                '301',
                '302',
                '303',
                '304',
                '306',
                '308',
                '309',
                '310',
                '311',
                '312',
                '313',
                '316',
                '317',
                '318',
                '320',
                '321',
                '323',
                '324',
                '325',
                '326',
                '327',
                '328',
                '329',
                '330',
                '331',
                '332',
                '333',
                '334',
                '335',
                '336',
                '337',
                '338',
                '339',
                '340',
                '342',
                '343',
                '344',
                '345',
                '346',
                '347',
                '348',
                '354',
                '355',
                '356',
                '361',
                '362',
                '363',
                '364',
                '365',
                '366',
                '367',
                '368',
                '369',
                '370',
                '371',
                '372',
                '373',
                '374',
                '375',
                '377',
                '379',
                '383',
                '384',
                '385',
                '386',
                '387',
                '388',
                '390',
                '391',
                '393',
                '395',
                '396',
                '397',
                '406',
                '407',
                '408',
                '409',
                '410',
                '411',
                '412',
                '413',
                '414',
                '415',
                '416',
                '417',
                '418',
                '419',
                '420',
                '421',
                '422',
                '423',
                '706',
                '709',
                '711',
                '712',
                '714',
                '715',
                '717',
                '728',
                '729',
                '731',
                '733',
                '734',
                '739',
                '740',
                '742',
                '743',
                '744',
                '745',
                '746',
                '747',
                '748',
                '749',
            ];
            $msg = '';
            break;

        default:
            $arrayPersonalized = [];
            break;
    }

    $arrayEnd = $stringArray;
    if (count($stringArray) == 0) {
        $arrayEnd = $arrayPersonalized;
    }

    if (! in_array($dataTxt[$key], $arrayEnd, 1)) {

        $array = implode(', ', $arrayEnd);

        $errorMessages[] = [
            'validacion' => 'searchInArray',
            'validacion_type_Y' => $validacion_type_Y,
            'num_invoice' => isset($dataExtra[Constants::KEY_NUMFACT]) ? $dataExtra[Constants::KEY_NUMFACT] : null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => $key,
            'data' => $dataTxt[$key],
            'error' => "El campo $key debe contener uno de los siguientes valores: $array . $msg.",
        ];

        return false;
    }

    return true;
}

function notNull($dataTxt, $key, &$errorMessages, $dataExtra = null, $validacion_type_Y = 'R')
{
    if (empty($dataTxt[$key])) {

        $errorMessages[] = [
            'validacion' => 'notNull',
            'validacion_type_Y' => $validacion_type_Y,
            'num_invoice' => isset($dataExtra[Constants::KEY_NUMFACT]) ? $dataExtra[Constants::KEY_NUMFACT] : null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => $key,
            'data' => $dataTxt[$key],
            'error' => "El campo $key no puede estar vacio.",
        ];

        return false;
    }

    return true;
}

function validateStringRange($dataTxt, $key, &$errorMessages, $min = 0, $max = 999, $dataExtra = null, $validacion_type_Y = 'R')
{
    $length = mb_strlen(strval($dataTxt[$key]), 'UTF-8');

    if (! ($length >= $min && $length <= $max)) {
        $errorMessages[] = [
            'validacion' => 'validateStringRange',
            'validacion_type_Y' => $validacion_type_Y,
            'num_invoice' => isset($dataExtra[Constants::KEY_NUMFACT]) ? $dataExtra[Constants::KEY_NUMFACT] : null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => $key,
            'data' => $dataTxt[$key],
            'error' => "El campo $key debe contener mínimo $min y máximo $max caracteres.",
        ];

        return false;
    }

    return true;
}

function validateField_codPais($dataTxt, $key, &$errorMessages, $dataExtra = null, $validacion_type_Y = 'R')
{

    $country = Pais::where('codigo', $dataTxt[$key])->first();

    if (! $country) {
        $errorMessages[] = [
            'validacion' => 'validateField_codPaisOrigen',
            'validacion_type_Y' => $validacion_type_Y,
            'num_invoice' => isset($dataExtra[Constants::KEY_NUMFACT]) ? $dataExtra[Constants::KEY_NUMFACT] : null,
            'file' => $dataTxt['file_name'] ?? null,
            'row' => $dataTxt['row'] ?? null,
            'column' => $key,
            'data' => $dataTxt[$key],
            'error' => "El campo $key no existe en la tabla Pais de la BD.",
        ];

        return false;
    }

    return true;
}
