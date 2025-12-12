<?php

function validateDataFilesExcel($arrayInfo, &$arrayData)
{
    $arrayExito = [];
    $dataExtra = ['numFactura' => null];
    $errorMessages = [];
    foreach ($arrayInfo as $indexInvoice => $data) {
        $arrayExito = [];
        $dataExtra = ['numFactura' => $data['numFactura']];

        // NO TIENEN VALIDACION AUN
        // Asignar una cadena vacía en lugar de null si $data['numNota'] es cero
        $arrayData[$indexInvoice]['numNota'] = ($data['numNota'] === '00') ? '' : $data['numNota'];
        // /////////////////////

        // Asignar una cadena vacía en lugar de null si $data['numNota'] es cero, si viene otro valor ejecuta la validacion
        if (($data['TipoNota'] === '00')) {
            $arrayData[$indexInvoice]['TipoNota'] = '';
        } else {
            $arrayExito[] = $errorValidation = searchInArray($data, 'TipoNota', $errorMessages, $dataExtra);
            if ($errorValidation) {
                $arrayData[$indexInvoice]['TipoNota'] = ($data['TipoNota'] === '00') ? '' : $data['TipoNota'];
            }
        }

        if (isset($data['usuarios']) && count($data['usuarios']) > 0) {
            foreach ($data['usuarios'] as $indexUser => $usuario) {

                // NO TIENEN VALIDACION AUN
                // /////////////////////

                if (
                    validateStringRange($usuario, 'codPaisOrigen', $errorMessages, 3, 3, $dataExtra) &&
                    validateField_codPais($usuario, 'codPaisOrigen', $errorMessages, $dataExtra)
                ) {
                    $arrayData[$indexInvoice]['usuarios'][$indexUser]['codPaisOrigen'] = $usuario['codPaisOrigen'];
                }

                if (
                    validateStringRange($usuario, 'codPaisResidencia', $errorMessages, 3, 3, $dataExtra) &&
                    validateField_codPais($usuario, 'codPaisResidencia', $errorMessages, $dataExtra)
                ) {
                    $arrayData[$indexInvoice]['usuarios'][$indexUser]['codPaisResidencia'] = $usuario['codPaisResidencia'];
                }

                $errorValidation = searchInArray($usuario, 'codZonaTerritorialResidencia', $errorMessages, $dataExtra);
                if ($errorValidation) {
                    $arrayData[$indexInvoice]['usuarios'][$indexUser]['codZonaTerritorialResidencia'] = $usuario['codZonaTerritorialResidencia'];
                }

                $errorValidation = validateYesNo($usuario, 'incapacidad', $errorMessages, $dataExtra);
                if ($errorValidation) {
                    $arrayData[$indexInvoice]['usuarios'][$indexUser]['incapacidad'] = $usuario['incapacidad'];
                }

                // debe prevalecer primero  la validacion personalizada
                $errorValidation = validationFormatDate($usuario, 'fechaNacimiento', $errorMessages, $dataExtra, 'R', 2);
                if ($errorValidation) {
                    $errorValidation1 = RVC006($usuario, $errorMessages); // confirmado!!
                    $errorValidation2 = RVC008($usuario, $errorMessages); // confirmado
                    $errorValidation3 = RVC007($usuario, $errorMessages, $dataExtra); // confirmado!!

                    if ($errorValidation1['result'] && $errorValidation2['result'] && $errorValidation3['result']) {
                        $arrayData[$indexInvoice]['usuarios'][$indexUser]['fechaNacimiento'] = $usuario['fechaNacimiento'];
                    }
                }

                // CONSULTAS
                if (isset($usuario['servicios']['consultas']) && count($usuario['servicios']['consultas']) > 0) {
                    foreach ($usuario['servicios']['consultas'] as $indexQuery => $consulta) {

                        // NO TIENEN VALIDACION AUN
                        $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['consultas'][$indexQuery]['tipoDocumentoIdentificacion'] = $consulta['tipoDocumentoIdentificacion'];
                        $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['consultas'][$indexQuery]['numDocumentoIdentificacion'] = $consulta['numDocumentoIdentificacion'];
                        $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['consultas'][$indexQuery]['numFEVPagoModerador'] = $consulta['numFEVPagoModerador'];
                        // /////////////////////

                        if (notNull($consulta, 'conceptoRecaudo', $errorMessages, $dataExtra)) {
                            $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['consultas'][$indexQuery]['conceptoRecaudo'] = $consulta['conceptoRecaudo'];
                        }

                        $errorValidation = searchInArray($consulta, 'modalidadGrupoServicioTecSal', $errorMessages, $dataExtra);
                        if ($errorValidation) {
                            $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['consultas'][$indexQuery]['modalidadGrupoServicioTecSal'] = $consulta['modalidadGrupoServicioTecSal'];
                        }

                        $errorValidation = searchInArray($consulta, 'grupoServicios', $errorMessages, $dataExtra);
                        if ($errorValidation) {
                            $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['consultas'][$indexQuery]['grupoServicios'] = $consulta['grupoServicios'];
                        }

                        $errorValidation = searchInArray($consulta, 'codServicio', $errorMessages, $dataExtra);
                        if ($errorValidation) {
                            $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['consultas'][$indexQuery]['codServicio'] = $consulta['codServicio'];
                        }

                        // debe prevalecer primero  la validacion personalizada
                        $errorValidation = validationFormatDate($consulta, 'fechaInicioAtencion', $errorMessages, $dataExtra);
                        if ($errorValidation) {

                            $errorValidation1 = RVC013($consulta, 'fechaInicioAtencion', $errorMessages); // confirmado

                            $errorValidation2 = RVC018($usuario['servicios']['consultas'], $consulta, 'codConsulta', $errorMessages); // confirmado

                            if ($errorValidation1['result'] && $errorValidation2['result']) {
                                $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['consultas'][$indexQuery]['fechaInicioAtencion'] = $consulta['fechaInicioAtencion'];
                            }

                        }

                        // debe prevalecer primero  la validacion personalizada
                        $errorValidation = onlyNumbers($consulta, 'valorPagoModerador', $errorMessages, $dataExtra);
                        if ($errorValidation) {
                            $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['consultas'][$indexQuery]['valorPagoModerador'] = $consulta['valorPagoModerador'];
                        }
                    }
                }

                // PROCEDIMIENTOS
                if (isset($usuario['servicios']['procedimientos']) && count($usuario['servicios']['procedimientos']) > 0) {
                    foreach ($usuario['servicios']['procedimientos'] as $indexProcedure => $procedimiento) {

                        // NO TIENEN VALIDACION AUN
                        $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['procedimientos'][$indexProcedure]['tipoDocumentoIdentificacion'] = $procedimiento['tipoDocumentoIdentificacion'];
                        $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['procedimientos'][$indexProcedure]['numDocumentoIdentificacion'] = $procedimiento['numDocumentoIdentificacion'];
                        $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['procedimientos'][$indexProcedure]['numFEVPagoModerador'] = $procedimiento['numFEVPagoModerador'];
                        // /////////////////////////////////

                        if (notNull($procedimiento, 'conceptoRecaudo', $errorMessages, $dataExtra)) {
                            $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['procedimientos'][$indexProcedure]['conceptoRecaudo'] = $procedimiento['conceptoRecaudo'];
                        }

                        // Verificar si 'idMIPRES' está presente y tiene una longitud válida
                        if (
                            notNull($procedimiento, 'idMIPRES', $errorMessages, $dataExtra) ||
                            validateStringRange($procedimiento, 'idMIPRES', $errorMessages, 0, 15, $dataExtra)
                        ) {
                            $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['procedimientos'][$indexProcedure]['idMIPRES'] = $procedimiento['idMIPRES'];
                        }

                        // debe prevalecer primero  la validacion personalizada
                        $errorValidation = onlyNumbers($procedimiento, 'valorPagoModerador', $errorMessages, $dataExtra);
                        if ($errorValidation) {
                            $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['procedimientos'][$indexProcedure]['valorPagoModerador'] = $procedimiento['valorPagoModerador'];
                        }

                        $errorValidation = searchInArray($procedimiento, 'modalidadGrupoServicioTecSal', $errorMessages, $dataExtra);
                        if ($errorValidation) {
                            $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['procedimientos'][$indexProcedure]['modalidadGrupoServicioTecSal'] = $procedimiento['modalidadGrupoServicioTecSal'];
                        }

                        $errorValidation = searchInArray($procedimiento, 'grupoServicios', $errorMessages, $dataExtra);
                        if ($errorValidation) {
                            $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['procedimientos'][$indexProcedure]['grupoServicios'] = $procedimiento['grupoServicios'];
                        }

                        $errorValidation = searchInArray($procedimiento, 'codServicio', $errorMessages, $dataExtra);
                        if ($errorValidation) {
                            $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['procedimientos'][$indexProcedure]['codServicio'] = $procedimiento['codServicio'];
                        }

                        // debe prevalecer primero  la validacion personalizada
                        $errorValidation = validationFormatDate($procedimiento, 'fechaInicioAtencion', $errorMessages, $dataExtra);
                        if ($errorValidation) {

                            $errorValidation1 = RVC013($procedimiento, 'fechaInicioAtencion', $errorMessages); // confirmado

                            $errorValidation2 = RVC018($usuario['servicios']['procedimientos'], $procedimiento, 'codProcedimiento', $errorMessages); // confirmado

                            if ($errorValidation1['result'] && $errorValidation2['result']) {
                                $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['procedimientos'][$indexProcedure]['fechaInicioAtencion'] = $procedimiento['fechaInicioAtencion'];
                            }

                        }
                    }
                }

                // OTROS SERVICIOS
                if (isset($usuario['servicios']['otrosServicios']) && count($usuario['servicios']['otrosServicios']) > 0) {
                    foreach ($usuario['servicios']['otrosServicios'] as $indexOtherService => $otrosServicio) {

                        // NO TIENEN VALIDACION AUN
                        $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['otrosServicios'][$indexOtherService]['tipoDocumentoIdentificacion'] = $otrosServicio['tipoDocumentoIdentificacion'];
                        $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['otrosServicios'][$indexOtherService]['numDocumentoIdentificacion'] = $otrosServicio['numDocumentoIdentificacion'];
                        $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['otrosServicios'][$indexOtherService]['numFEVPagoModerador'] = $otrosServicio['numFEVPagoModerador'];

                        // /////////////////////////////////

                        if (notNull($otrosServicio, 'conceptoRecaudo', $errorMessages, $dataExtra)) {
                            $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['otrosServicios'][$indexOtherService]['conceptoRecaudo'] = $otrosServicio['conceptoRecaudo'];
                        }

                        // Verificar si 'idMIPRES' está presente y tiene una longitud válida
                        if (
                            notNull($otrosServicio, 'idMIPRES', $errorMessages, $dataExtra) ||
                            validateStringRange($otrosServicio, 'idMIPRES', $errorMessages, 0, 15, $dataExtra)
                        ) {
                            $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['otrosServicios'][$indexOtherService]['idMIPRES'] = $otrosServicio['idMIPRES'];
                        }

                        // debe prevalecer primero  la validacion personalizada
                        $errorValidation = onlyNumbers($otrosServicio, 'valorPagoModerador', $errorMessages, $dataExtra);
                        if ($errorValidation) {
                            $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['otrosServicios'][$indexOtherService]['valorPagoModerador'] = $otrosServicio['valorPagoModerador'];
                        }

                        // debe prevalecer primero  la validacion personalizada
                        $errorValidation = validationFormatDate($otrosServicio, 'fechaSuministroTecnologia', $errorMessages, $dataExtra);
                        if ($errorValidation) {
                            $errorValidation = RVC013($otrosServicio, 'fechaSuministroTecnologia', $errorMessages); // confirmado
                            if ($errorValidation['result']) {
                                $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['otrosServicios'][$indexOtherService]['fechaSuministroTecnologia'] = $otrosServicio['fechaSuministroTecnologia'];
                            }
                        }
                    }
                }

                // URGENCIAS
                if (isset($usuario['servicios']['urgencias']) && count($usuario['servicios']['urgencias']) > 0) {
                    foreach ($usuario['servicios']['urgencias'] as $indexUrgency => $urgencia) {
                        // NO TIENEN VALIDACION AUN
                        // ///////////////////////////////

                        $errorValidation = validationFormatDate($urgencia, 'fechaEgreso', $errorMessages, $dataExtra);
                        if ($errorValidation) {
                            $errorValidation1 = RVC043($urgencia, 'fechaEgreso', $errorMessages); // confirmado
                            $errorValidation2 = RVC044($urgencia, 'fechaEgreso', $errorMessages); // confirmado

                            if ($errorValidation1['result'] && $errorValidation2['result']) {
                                $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['urgencias'][$indexUrgency]['fechaEgreso'] = $urgencia['fechaEgreso'];
                            }
                        }

                        $errorValidation = validationFormatDate($urgencia, 'fechaInicioAtencion', $errorMessages, $dataExtra);
                        if ($errorValidation) {

                            $errorValidation1 = RVC038($urgencia, 'fechaInicioAtencion', $errorMessages); // confirmado
                            $errorValidation2 = RVC039($urgencia, 'fechaInicioAtencion', 'fechaEgreso', $errorMessages); // confirmado
                            $errorValidation3 = RVC040($urgencia, 'fechaInicioAtencion', 'fechaEgreso', $errorMessages); // confirmado

                            if ($errorValidation1['result'] && $errorValidation2['result'] && $errorValidation3['result']) {
                                $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['urgencias'][$indexUrgency]['fechaInicioAtencion'] = $urgencia['fechaInicioAtencion'];
                            }
                        }
                    }
                }

                // HOSPITALIZACION
                if (isset($usuario['servicios']['hospitalizacion']) && count($usuario['servicios']['hospitalizacion']) > 0) {
                    foreach ($usuario['servicios']['hospitalizacion'] as $indexHospitalization => $hospitalizacion) {
                        // NO TIENEN VALIDACION AUN
                        // ///////////////////////

                        $errorValidation = validationFormatDate($hospitalizacion, 'fechaEgreso', $errorMessages, $dataExtra);
                        if ($errorValidation) {

                            $errorValidation1 = RVC043($hospitalizacion, 'fechaEgreso', $errorMessages); // confirmado
                            $errorValidation2 = RVC044($hospitalizacion, 'fechaEgreso', $errorMessages); // confirmado

                            if ($errorValidation1['result'] && $errorValidation2['result']) {
                                $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['hospitalizacion'][$indexHospitalization]['fechaEgreso'] = $hospitalizacion['fechaEgreso'];
                            }
                        }

                        $errorValidation = validationFormatDate($hospitalizacion, 'fechaInicioAtencion', $errorMessages, $dataExtra);
                        if ($errorValidation) {

                            $errorValidation1 = RVC038($hospitalizacion, 'fechaInicioAtencion', $errorMessages); // confirmado
                            $errorValidation2 = RVC039($hospitalizacion, 'fechaInicioAtencion', 'fechaEgreso', $errorMessages); // confirmado

                            if ($errorValidation1['result'] && $errorValidation2['result']) {
                                $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['hospitalizacion'][$indexHospitalization]['fechaInicioAtencion'] = $hospitalizacion['fechaInicioAtencion'];
                            }
                        }
                    }
                }

                // RECIEN NACIDOS
                if (isset($usuario['servicios']['recienNacidos']) && count($usuario['servicios']['recienNacidos']) > 0) {
                    foreach ($usuario['servicios']['recienNacidos'] as $indexNewlyBorn => $recienNacido) {
                        // NO TIENEN VALIDACION AUN

                        $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['recienNacidos'][$indexNewlyBorn]['tipoDocumentoIdentificacion'] = $recienNacido['tipoDocumentoIdentificacion'];
                        $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['recienNacidos'][$indexNewlyBorn]['numConsultasCPrenatal'] = $recienNacido['numConsultasCPrenatal'];
                        // ///////////////////////

                        $errorValidation = validationFormatDate($recienNacido, 'fechaNacimiento', $errorMessages, $dataExtra, 'R', 2);
                        if ($errorValidation) {
                            $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['recienNacidos'][$indexNewlyBorn]['fechaNacimiento'] = $recienNacido['fechaNacimiento'];
                        }

                        $errorValidation = validationFormatDate($recienNacido, 'fechaEgreso', $errorMessages, $dataExtra);
                        if ($errorValidation) {
                            $errorValidation1 = RVC043($recienNacido, 'fechaEgreso', $errorMessages); // confirmado
                            $errorValidation2 = RVC044($recienNacido, 'fechaEgreso', $errorMessages); // confirmado
                            $errorValidation3 = RVC046($recienNacido, 'fechaEgreso', 'fechaNacimiento', $errorMessages); // confirmado

                            if ($errorValidation1['result'] && $errorValidation2['result'] && $errorValidation3['result']) {
                                $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['recienNacidos'][$indexNewlyBorn]['fechaEgreso'] = $recienNacido['fechaEgreso'];
                            }
                        }
                    }
                }

                // MEDICAMENTOS
                if (isset($usuario['servicios']['medicamentos']) && count($usuario['servicios']['medicamentos']) > 0) {
                    foreach ($usuario['servicios']['medicamentos'] as $indexMedicine => $medicamento) {

                        // NO TIENEN VALIDACION AUN
                        $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['medicamentos'][$indexMedicine]['formaFarmaceutica'] = $medicamento['formaFarmaceutica'];
                        $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['medicamentos'][$indexMedicine]['unidadMinDispensa'] = $medicamento['unidadMinDispensa'];
                        $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['medicamentos'][$indexMedicine]['tipoDocumentoIdentificacion'] = $medicamento['tipoDocumentoIdentificacion'];
                        $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['medicamentos'][$indexMedicine]['numDocumentoIdentificacion'] = $medicamento['numDocumentoIdentificacion'];
                        $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['medicamentos'][$indexMedicine]['vrUnitMedicamento'] = $medicamento['vrUnitMedicamento'];
                        $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['medicamentos'][$indexMedicine]['numFEVPagoModerador'] = $medicamento['numFEVPagoModerador'];
                        // ///////////////////////

                        if (notNull($medicamento, 'conceptoRecaudo', $errorMessages, $dataExtra)) {
                            $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['medicamentos'][$indexMedicine]['conceptoRecaudo'] = $medicamento['conceptoRecaudo'];
                        }

                        // Verificar si 'idMIPRES' está presente y tiene una longitud válida
                        if (
                            notNull($medicamento, 'idMIPRES', $errorMessages, $dataExtra) ||
                            validateStringRange($medicamento, 'idMIPRES', $errorMessages, 0, 15, $dataExtra)
                        ) {
                            $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['medicamentos'][$indexMedicine]['idMIPRES'] = $medicamento['idMIPRES'];
                        }

                        // debe prevalecer primero  la validacion personalizada
                        $errorValidation = onlyNumbers($medicamento, 'valorPagoModerador', $errorMessages, $dataExtra);
                        if ($errorValidation) {
                            $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['medicamentos'][$indexMedicine]['valorPagoModerador'] = $medicamento['valorPagoModerador'];
                        }

                        $errorValidation = onlyNumbers($medicamento, 'diasTratamiento', $errorMessages, $dataExtra);
                        if ($errorValidation) {
                            $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['medicamentos'][$indexMedicine]['diasTratamiento'] = $medicamento['diasTratamiento'];
                        }

                        // debe prevalecer primero  la validacion personalizada

                        $errorValidation = validationFormatDate($medicamento, 'fechaDispensAdmon', $errorMessages, $dataExtra);
                        if ($errorValidation) {
                            $errorValidation = RVC013($medicamento, 'fechaDispensAdmon', $errorMessages); // confirmado
                            if ($errorValidation['result']) {
                                $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['medicamentos'][$indexMedicine]['fechaDispensAdmon'] = $medicamento['fechaDispensAdmon'];
                            }
                        }

                        $errorValidation = RVC028($medicamento, 'codDiagnosticoPrincipal', $usuario, $errorMessages); // confirmado
                        if ($errorValidation['result']) {
                            $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['medicamentos'][$indexMedicine]['codDiagnosticoPrincipal'] = $medicamento['codDiagnosticoPrincipal'];
                        }
                        if (! empty($medicamento['codDiagnosticoRelacionado'])) {
                            $errorValidation = RVC028($medicamento, 'codDiagnosticoRelacionado', $usuario, $errorMessages); // confirmado
                            if ($errorValidation['result']) {
                                $arrayData[$indexInvoice]['usuarios'][$indexUser]['servicios']['medicamentos'][$indexMedicine]['codDiagnosticoRelacionado'] = $medicamento['codDiagnosticoRelacionado'];
                            }
                        }
                    }
                }
            }
        }
    }

    return [
        'errorMessages' => $errorMessages,
        'totalErrorMessages' => count($errorMessages),

    ];
}

function validateNullExcel($xlsCollection)
{
    $resultado = array_filter($xlsCollection->toArray(), function ($registro) {
        // Filtra los registros donde el campo "valor" es nulo o vacío
        return is_null($registro['valor']) || $registro['valor'] === '';
    });

    $resultado = array_values($resultado);

    // Mapea los resultados según el formato deseado
    return array_map(function ($value, $key) {
        return [
            'file' => $value['file'],
            'row' => $key + 2, // +1 porque los índices de arrays en PHP comienzan desde 0
            'column' => $value['campo'],
            'data' => null,
            'error' => 'El campo no puede estar vacío',
        ];
    }, $resultado, array_keys($resultado));
}
