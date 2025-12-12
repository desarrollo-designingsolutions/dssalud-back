<?php

function validateDataFilesTxt($arrayData)
{
    $errorMessages = [];
    $successfulInvoices = [];
    $failedInvoices = [];
    $arrayExito = [];

    $invoicesWithErrors = [];
    $invoicesWithoutErrors = [];
    // return $arrayData;
    foreach ($arrayData as $ff => $data) {
        $arrayExito = [];

        $dataExtra = ['numFactura' => $data['numFactura']];

        // return $data;
        $arrayExito[] = RVC002($data, $errorMessages);

        $arrayExito[] = RVC003($data, $errorMessages);

        if (isset($data['usuarios']) && count($data['usuarios']) > 0) {
            foreach ($data['usuarios'] as $usuario) {
                // $usuario["codSexo"] = 2; //para pruebas
                $arrayExito[] = RVC009($usuario, $errorMessages, $dataExtra); // confirmado
                //     RVC010($usuario, 123, $errorMessages); //con que se valida no entiendo

                $arrayExito[] = [
                    'validacion_type_Y' => 'R',
                    'result' => searchInArray($usuario, 'codZonaTerritorialResidencia', $errorMessages, $dataExtra, 'R'),
                ];

                // CONSULTAS
                // return $usuario["servicios"]["consultas"];
                if (isset($usuario['servicios']['consultas']) && count($usuario['servicios']['consultas']) > 0) {
                    foreach ($usuario['servicios']['consultas'] as $consulta) {
                        // return $consulta;

                        $arrayExito[] = RVC011($consulta, 'codPrestador', $errorMessages); // confirmado

                        // $arrayExito[] = RVC012($consulta,'codPrestador', $errorMessages); // no se entiende la validacion

                        $arrayExito[] = RVC015($consulta, 'codConsulta', $usuario, $errorMessages); // confirmado

                        $arrayExito[] = RVC016($consulta, 'codConsulta', $usuario, $errorMessages); // confirmado

                        $arrayExito[] = RVC019($consulta, 'codConsulta', $errorMessages); // confirmado

                        $arrayExito[] = RVC027($consulta, 'codConsulta', $usuario, $errorMessages); // confirmado

                        $arrayExito[] = RVC028($consulta, 'codDiagnosticoPrincipal', $usuario, $errorMessages); // confirmado

                        if (! empty($consulta['codDiagnosticoRelacionado1'])) {
                            $arrayExito[] = RVC028($consulta, 'codDiagnosticoRelacionado1', $usuario, $errorMessages); // confirmado
                        }
                        if (! empty($consulta['codDiagnosticoRelacionado2'])) {
                            $arrayExito[] = RVC028($consulta, 'codDiagnosticoRelacionado2', $usuario, $errorMessages); // confirmado
                        }
                        if (! empty($consulta['codDiagnosticoRelacionado3'])) {
                            $arrayExito[] = RVC028($consulta, 'codDiagnosticoRelacionado3', $usuario, $errorMessages); // confirmado
                        }

                        // $arrayExito[] = RVC029($consulta,"codDiagnosticoPrincipal", $errorMessages); //no se entiende aqui que hay que validar
                        // $arrayExito[] = RVC029($consulta,"codDiagnosticoRelacionado1", $errorMessages); //no se entiende aqui que hay que validar
                        // $arrayExito[] = RVC029($consulta,"codDiagnosticoRelacionado2", $errorMessages); //no se entiende aqui que hay que validar
                        // $arrayExito[] = RVC029($consulta,"codDiagnosticoRelacionado3", $errorMessages); //no se entiende aqui que hay que validar
                        // $arrayExito[] = RVC031($consulta,"codDiagnosticoPrincipal", $errorMessages); //no se entiende aqui que hay que validar
                        // $arrayExito[] = RVC034($consulta,"vrServicio", $errorMessages); //no se entiende aqui que hay que validar

                    }
                }

                // PROCEDIMIENTOS
                if (isset($usuario['servicios']['procedimientos']) && count($usuario['servicios']['procedimientos']) > 0) {
                    foreach ($usuario['servicios']['procedimientos'] as $procedimiento) {

                        // ******* EMPEZAR A RECONFIRMAR DESDE AQUI
                        // *******

                        // return $procedimiento;
                        $arrayExito[] = RVC011($procedimiento, 'codPrestador', $errorMessages); // confirmado
                        // $arrayExito[] = RVC012($procedimiento,'codPrestador', $errorMessages); // no se entiende la validacion

                        $arrayExito[] = RVC016($procedimiento, 'codProcedimiento', $usuario, $errorMessages); // confirmado

                        $arrayExito[] = RVC019($procedimiento, 'codProcedimiento', $errorMessages); // confirmado

                        $arrayExito[] = RVC020($procedimiento, $errorMessages); // confirmado
                        // $procedimiento["codProcedimiento"] = "010100"; // para pruebas
                        $arrayExito[] = RVC021($procedimiento, $usuario, $errorMessages); // confirmado
                        // $arrayExito[] = RVC022($procedimiento,  $errorMessages); //no se entiende aqui que hay que validar
                        // $arrayExito[] = RVC023($procedimiento,  $errorMessages); //no se entiende aqui que hay que validar

                        $arrayExito[] = RVC028($procedimiento, 'codDiagnosticoPrincipal', $usuario, $errorMessages); // confirmado

                        if (! empty($procedimiento['codDiagnosticoRelacionado'])) {
                            $arrayExito[] = RVC028($procedimiento, 'codDiagnosticoRelacionado', $usuario, $errorMessages); // confirmado
                        }
                        if (! empty($procedimiento['codComplicacion'])) {
                            $arrayExito[] = RVC028($procedimiento, 'codComplicacion', $usuario, $errorMessages); // confirmado
                        }
                        // $arrayExito[] = RVC031($procedimiento,"codDiagnosticoPrincipal", $errorMessages); //no se entiende aqui que hay que validar
                        // $arrayExito[] = RVC031($procedimiento,"codComplicacion", $errorMessages); //no se entiende aqui que hay que validar
                        // $arrayExito[] = RVC032($procedimiento,"codDiagnosticoPrincipal", $errorMessages); //no se entiende aqui que hay que validar
                        // $arrayExito[] = RVC032($procedimiento,"codComplicacion", $errorMessages); //no se entiende aqui que hay que validar

                        // $arrayExito[] = RVC033($procedimiento,"codDiagnosticoPrincipal", $errorMessages); //no se entiende aqui que hay que validar
                        // $arrayExito[] = RVC033($procedimiento,"codComplicacion", $errorMessages); //no se entiende aqui que hay que validar
                        // $arrayExito[] = RVC034($procedimiento,"vrServicio", $errorMessages); //no se entiende aqui que hay que validar

                    }
                }

                // OTROS SERVICIOS
                if (isset($usuario['servicios']['otrosServicios']) && count($usuario['servicios']['otrosServicios']) > 0) {
                    foreach ($usuario['servicios']['otrosServicios'] as $otrosServicio) {
                        // return $otrosServicio;

                        // pendiente por verificacion
                        // $arrayExito[] = RVC016($otrosServicio, 'codTecnologiaSalud', $usuario, $errorMessages); //confirmado
                        //

                        // $arrayExito[] = RVC019($otrosServicio, 'codConsulta', $errorMessages); //confirmado

                        // EN ESPERA DE VALIDACION CON EL USUARIO
                        // $arrayExito[] = RVC024($otrosServicio, $errorMessages); //confirmado

                        // $arrayExito[] = RVC025($otrosServicio, $errorMessages); //no se entiende aqui que hay que validar
                        // $otrosServicio['codTecnologiaSalud'] = '602A02'; //para pruebas
                        // EN ESPERA DE VALIDACION CON EL USUARIO
                        // $arrayExito[] = RVC026($otrosServicio, $errorMessages); //confirmado
                        // $arrayExito[] = RVC034($otrosServicio,"vrUnitOS", $errorMessages); //no se entiende aqui que hay que validar
                    }
                }

                // URGENCIAS
                if (isset($usuario['servicios']['urgencias']) && count($usuario['servicios']['urgencias']) > 0) {
                    foreach ($usuario['servicios']['urgencias'] as $urgencia) {
                        // return $urgencia;
                        $arrayExito[] = RVC028($urgencia, 'codDiagnosticoPrincipal', $usuario, $errorMessages); // confirmado
                        $arrayExito[] = RVC028($urgencia, 'codDiagnosticoPrincipalE', $usuario, $errorMessages); // confirmado

                        if (! empty($urgencia['codDiagnosticoRelacionadoE1'])) {

                            $arrayExito[] = RVC028($urgencia, 'codDiagnosticoRelacionadoE1', $usuario, $errorMessages); // confirmado
                        }
                        if (! empty($urgencia['codDiagnosticoRelacionadoE2'])) {

                            $arrayExito[] = RVC028($urgencia, 'codDiagnosticoRelacionadoE2', $usuario, $errorMessages); // confirmado
                        }
                        if (! empty($urgencia['codDiagnosticoRelacionadoE3'])) {

                            $arrayExito[] = RVC028($urgencia, 'codDiagnosticoRelacionadoE3', $usuario, $errorMessages); // confirmado
                        }
                        if (! empty($urgencia['codDiagnosticoCausaMuerte'])) {

                            $arrayExito[] = RVC028($urgencia, 'codDiagnosticoCausaMuerte', $usuario, $errorMessages); // confirmado
                        }
                        // $arrayExito[] = RVC029($urgencia,"codDiagnosticoPrincipal",123, $errorMessages); //no se entiende aqui que hay que validar
                        // $arrayExito[] = RVC029($urgencia,"codDiagnosticoPrincipalE",123, $errorMessages); //no se entiende aqui que hay que validar
                        // $arrayExito[] = RVC031($urgencia,"codDiagnosticoPrincipal", $errorMessages); //no se entiende aqui que hay que validar
                        // $arrayExito[] = RVC031($urgencia,"codDiagnosticoPrincipalE", $errorMessages); //no se entiende aqui que hay que validar
                        // $arrayExito[] = RVC031($urgencia,"codDiagnosticoCausaMuerte", $errorMessages); //no se entiende aqui que hay que validar
                        // $arrayExito[] = RVC032($urgencia,"codDiagnosticoPrincipal", $errorMessages); //no se entiende aqui que hay que validar
                        // $arrayExito[] = RVC032($urgencia,"codDiagnosticoCausaMuerte", $errorMessages); //no se entiende aqui que hay que validar
                        // $arrayExito[] = RVC033($urgencia,"codDiagnosticoPrincipal", $errorMessages); //no se entiende aqui que hay que validar
                        // $arrayExito[] = RVC033($urgencia,"codDiagnosticoCausaMuerte", $errorMessages); //no se entiende aqui que hay que validar

                        // $urgencia["condicionDestinoUsuarioEgreso"] = 2; // para pruebas
                        $arrayExito[] = RVC042($urgencia, 'condicionDestinoUsuarioEgreso', $errorMessages);  // confirmado
                    }
                }

                // HOSPITALIZACION
                if (isset($usuario['servicios']['hospitalizacion']) && count($usuario['servicios']['hospitalizacion']) > 0) {
                    foreach ($usuario['servicios']['hospitalizacion'] as $hospitalizacion) {
                        // return $hospitalizacion;
                        $arrayExito[] = RVC028($hospitalizacion, 'codDiagnosticoPrincipal', $usuario, $errorMessages); // confirmado
                        $arrayExito[] = RVC028($hospitalizacion, 'codDiagnosticoPrincipalE', $usuario, $errorMessages); // confirmado

                        if (! empty($hospitalizacion['codDiagnosticoRelacionadoE1'])) {
                            $arrayExito[] = RVC028($hospitalizacion, 'codDiagnosticoRelacionadoE1', $usuario, $errorMessages); // confirmado
                        }
                        if (! empty($hospitalizacion['codDiagnosticoRelacionadoE2'])) {
                            $arrayExito[] = RVC028($hospitalizacion, 'codDiagnosticoRelacionadoE2', $usuario, $errorMessages); // confirmado
                        }
                        if (! empty($hospitalizacion['codDiagnosticoRelacionadoE3'])) {
                            $arrayExito[] = RVC028($hospitalizacion, 'codDiagnosticoRelacionadoE3', $usuario, $errorMessages); // confirmado
                        }
                        if (! empty($hospitalizacion['codComplicacion'])) {
                            $arrayExito[] = RVC028($hospitalizacion, 'codComplicacion', $usuario, $errorMessages); // confirmado
                        }
                        if (! empty($hospitalizacion['codDiagnosticoCausaMuerte'])) {
                            $arrayExito[] = RVC028($hospitalizacion, 'codDiagnosticoCausaMuerte', 123, $errorMessages); // confirmado
                        }
                        // $arrayExito[] = RVC029($hospitalizacion,"codDiagnosticoPrincipal",123, $errorMessages); //no se entiende aqui que hay que validar
                        // $arrayExito[] = RVC029($hospitalizacion,"codDiagnosticoPrincipalE",123, $errorMessages); //no se entiende aqui que hay que validar
                        // $arrayExito[] = RVC031($hospitalizacion,"codDiagnosticoPrincipal", $errorMessages); //no se entiende aqui que hay que validar
                        // $arrayExito[] = RVC031($hospitalizacion,"codDiagnosticoPrincipalE", $errorMessages); //no se entiende aqui que hay que validar
                        // $arrayExito[] = RVC031($hospitalizacion,"codComplicacion", $errorMessages); //no se entiende aqui que hay que validar
                        // $arrayExito[] = RVC031($hospitalizacion,"codDiagnosticoCausaMuerte", $errorMessages); //no se entiende aqui que hay que validar
                        // $arrayExito[] = RVC032($hospitalizacion,"codDiagnosticoCausaMuerte", $errorMessages); //no se entiende aqui que hay que validar
                        // $arrayExito[] = RVC033($hospitalizacion,"codDiagnosticoCausaMuerte", $errorMessages); //no se entiende aqui que hay que validar

                        $arrayExito[] = RVC042($hospitalizacion, 'condicionDestinoUsuarioEgreso', $errorMessages); // no se entiende aqui que hay que validar
                    }
                }

                // RECIEN NACIDOS
                if (isset($usuario['servicios']['recienNacidos']) && count($usuario['servicios']['recienNacidos']) > 0) {
                    foreach ($usuario['servicios']['recienNacidos'] as $recienNacido) {
                        $arrayExito[] = RVC028($recienNacido, 'codDiagnosticoPrincipal', $usuario, $errorMessages); // confirmado

                        if (! empty($recienNacido['codDiagnosticoCausaMuerte'])) {
                            $arrayExito[] = RVC028($recienNacido, 'codDiagnosticoCausaMuerte', $usuario, $errorMessages); // confirmado
                        }

                        $arrayExito[] = [
                            'validacion_type_Y' => 'R',
                            'result' => validationFormatDate($recienNacido, 'fechaNacimiento', $errorMessages, $dataExtra, 'R', 2),
                        ];

                        // $arrayExito[] = RVC031($recienNacido,"codDiagnosticoPrincipal", $errorMessages); //no se entiende aqui que hay que validar
                        // $arrayExito[] = RVC031($recienNacido,"codDiagnosticoCausaMuerte", $errorMessages); //no se entiende aqui que hay que validar
                        // $arrayExito[] = RVC032($recienNacido,"codDiagnosticoCausaMuerte", $errorMessages); //no se entiende aqui que hay que validar
                        // $arrayExito[] = RVC033($recienNacido,"codDiagnosticoCausaMuerte", $errorMessages); //no se entiende aqui que hay que validar

                        // $recienNacido['condicionDestinoUsuarioEgreso'] = 2; // para pruebas
                        $arrayExito[] = RVC042($recienNacido, 'condicionDestinoUsuarioEgreso', $errorMessages); // no se entiende aqui que hay que validar
                    }
                }

                // MEDICAMENTOS
                if (isset($usuario['servicios']['medicamentos']) && count($usuario['servicios']['medicamentos']) > 0) {
                    foreach ($usuario['servicios']['medicamentos'] as $medicamento) {
                        // return $medicamento;

                        // $arrayExito[] = RVC031($medicamento,"codDiagnosticoPrincipal", $errorMessages); //no se entiende aqui que hay que validar
                        // $arrayExito[] = RVC034($medicamento,"vrUnitMedicamento", $errorMessages); //no se entiende aqui que hay que validar
                    }
                }
            }
        }

        if (count($arrayExito) > 0) {

            $resultValidation = has_any_validation_error($arrayExito);
            if ($resultValidation) {
                $invoicesWithErrors[] = $data['numFactura'];
            } else {
                $invoicesWithoutErrors[] = $data['numFactura'];
            }

            $resultValidation = all_validation_successful($arrayExito);
            if ($resultValidation) {
                $successfulInvoices[] = $data['numFactura'];
            } else {
                $failedInvoices[] = $data['numFactura'];
            }
        } else {
            // si no se verifica la factura con ninguna validacion entonces se agrega como aceptable
            $successfulInvoices[] = $data['numFactura'];
        }
    }

    // esto es para obtener las facturas completas, segun el array de facturas que pasan la validacion
    $resultado = array_filter($arrayData, function ($elemento) use ($successfulInvoices) {
        // Ajusta la propiedad numFactura según la estructura real de tus objetos
        $numFactura = $elemento['numFactura'];

        // Verifica si el numFactura está en el segundo array
        return in_array($numFactura, $successfulInvoices);
    });

    return [
        'totalInvoices' => count($arrayData),

        'jsonSuccessfullInvoices' => $resultado,
        'successfulInvoices' => $successfulInvoices,
        'totalSuccessfulInvoices' => count($successfulInvoices),
        'failedInvoices' => $failedInvoices,
        'totalFailedInvoices' => count($failedInvoices),
        'invoicesWithErrors' => $invoicesWithErrors,
        'invoicesWithoutErrors' => $invoicesWithoutErrors,
        'totalInvoicesWithErrors' => count($invoicesWithErrors),
        'totalInvoicesWithoutErrors' => count($invoicesWithoutErrors),
        'errorMessages' => $errorMessages,
        'totalErrorMessages' => count($errorMessages),
    ];
}

function all_validation_successful($validations)
{
    $allValidationsAreCorrect = true;

    foreach ($validations as $validation) {
        if ($validation['validacion_type_Y'] === 'R' && $validation['result'] === false) {
            $allValidationsAreCorrect = false;
            break;
        }
    }

    return $allValidationsAreCorrect;
}

function has_any_validation_error($validations)
{
    // Esta función solo verifica si hay al menos un error en las facturas. No toma en cuenta el tipo de validación.
    foreach ($validations as $validation) {
        if ($validation['result'] === false) {
            return true;
        }
    }

    return false;
}

function validations_txt_invoice($invoice)
{
    $errorMessages = [];
    $validation = RVC002($invoice, $errorMessages);
    if (! $validation['result']) {
        return [
            'result' => false,
            'result' => $errorMessages[0]['error'],
        ];
    }

    $validation = RVC003($invoice, $errorMessages);
    if ($validation['result']) {
        return [
            'result' => false,
            'result' => $errorMessages[0]['error'],
        ];
    }

    return [
        'result' => true,
        'result' => $errorMessages[0]['error'],
    ];
}
