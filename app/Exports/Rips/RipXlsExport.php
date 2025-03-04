<?php

namespace App\Exports\Rips;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

// use Maatwebsite\Excel\Concerns\WithCustomStartCell;
// use Maatwebsite\Excel\Events\AfterSheet;
// use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class RipXlsExport implements FromArray, WithHeadings, WithTitle
{
    protected $invoices;

    public function __construct($invoices)
    {
        $this->invoices = $invoices;
    }

    public function title(): string
    {
        return 'ListErrores'; // Cambia aquí al nombre que desees
    }

    public function array(): array
    {
        $data = [];

        // Agregar cada factura como una fila en el array de datos
        foreach ($this->invoices as $keyI => $invoice) {

            $requiredFields = ['TipoNota', 'numNota'];

            if (isset($invoice['numFactura'])) {

                // Verificar si faltan campos requeridos
                $this->formData($data, $requiredFields, $invoice, $invoice['numFactura']);

                //USUARIOS
                if (isset($invoice['usuarios']) && count($invoice['usuarios']) > 0) {
                    foreach ($invoice['usuarios'] as $keyU => $user) {
                        $requiredFields = ['codPaisOrigen', 'fechaNacimiento', 'codPaisResidencia', 'codZonaTerritorialResidencia', 'incapacidad'];
                        // Verificar si faltan campos requeridos
                        $this->formData($data, $requiredFields, $user, $invoice['numFactura'], $keyU + 1, $user['numDocumentoIdentificacion']);

                        //CONSULTAS
                        if (isset($user['servicios']['consultas']) && count($user['servicios']['consultas']) > 0) {
                            foreach ($user['servicios']['consultas'] as $keyC => $consulta) {
                                //fechaInicioAtencion NO ESTABA EN LA LISTA SE AGREGA POR PETICION DE GERMAN
                                $requiredFields = ['conceptoRecaudo', 'fechaInicioAtencion', 'modalidadGrupoServicioTecSal', 'grupoServicios', 'codServicio', 'tipoDocumentoIdentificacion', 'numDocumentoIdentificacion', 'valorPagoModerador', 'numFEVPagoModerador'];
                                // Verificar si faltan campos requeridos
                                $this->formData($data, $requiredFields, $consulta, $invoice['numFactura'], $keyU + 1, $user['numDocumentoIdentificacion'], $keyC + 1, 'consultas');
                            }
                        }

                        //PROCEDIMIENTOS
                        if (isset($user['servicios']['procedimientos']) && count($user['servicios']['procedimientos']) > 0) {
                            // dd($user["servicios"]["procedimientos"]);
                            foreach ($user['servicios']['procedimientos'] as $keyP => $value) {
                                //fechaInicioAtencion NO ESTABA EN LA LISTA SE AGREGA POR PETICION DE GERMAN
                                $requiredFields = ['conceptoRecaudo', 'fechaInicioAtencion', 'idMIPRES', 'modalidadGrupoServicioTecSal', 'grupoServicios', 'codServicio', 'tipoDocumentoIdentificacion', 'numDocumentoIdentificacion', 'valorPagoModerador', 'numFEVPagoModerador'];
                                // Verificar si faltan campos requeridos
                                $this->formData($data, $requiredFields, $value, $invoice['numFactura'], $keyU + 1, $user['numDocumentoIdentificacion'], $keyP + 1, 'procedimientos');
                            }
                        }

                        //MEDICAMENTOS
                        if (isset($user['servicios']['medicamentos']) && count($user['servicios']['medicamentos']) > 0) {
                            foreach ($user['servicios']['medicamentos'] as $keyM => $value) {
                                $requiredFields = ['conceptoRecaudo', 'idMIPRES', 'fechaDispensAdmon', 'codDiagnosticoPrincipal', 'codDiagnosticoRelacionado', 'formaFarmaceutica', 'unidadMinDispensa', 'diasTratamiento', 'tipoDocumentoIdentificacion', 'numDocumentoIdentificacion', 'vrUnitMedicamento',  'valorPagoModerador', 'numFEVPagoModerador'];
                                // Verificar si faltan campos requeridos
                                $this->formData($data, $requiredFields, $value, $invoice['numFactura'], $keyU + 1, $user['numDocumentoIdentificacion'], $keyM + 1, 'medicamentos');
                            }
                        }

                        //URGENCIAS
                        if (isset($user['servicios']['urgencias']) && count($user['servicios']['urgencias']) > 0) {
                            foreach ($user['servicios']['urgencias'] as $keyU => $value) {
                                //fechaInicioAtencion NO ESTABA EN LA LISTA SE AGREGA POR PETICION DE GERMAN
                                $requiredFields = ['consecutivo','fechaInicioAtencion'];
                                // Verificar si faltan campos requeridos
                                $this->formData($data, $requiredFields, $value, $invoice['numFactura'], $keyU + 1, $user['numDocumentoIdentificacion'], $keyU + 1, 'urgencias');
                            }
                        }

                        //OTROS SERVICIOS
                        if (isset($user['servicios']['otrosServicios']) && count($user['servicios']['otrosServicios']) > 0) {
                            foreach ($user['servicios']['otrosServicios'] as $keyOS => $value) {
                                $requiredFields = ['conceptoRecaudo', 'idMIPRES', 'fechaSuministroTecnologia', 'tipoDocumentoIdentificacion', 'numDocumentoIdentificacion', 'valorPagoModerador', 'numFEVPagoModerador'];
                                // Verificar si faltan campos requeridos
                                $this->formData($data, $requiredFields, $value, $invoice['numFactura'], $keyU + 1, $user['numDocumentoIdentificacion'], $keyOS + 1, 'otrosServicios');
                            }
                        }

                        //HOSPITALIZACION
                        if (isset($user['servicios']['hospitalizacion']) && count($user['servicios']['hospitalizacion']) > 0) {
                            foreach ($user['servicios']['hospitalizacion'] as $keyH => $value) {
                                //fechaInicioAtencion NO ESTABA EN LA LISTA SE AGREGA POR PETICION DE GERMAN
                                $requiredFields = ['consecutivo','fechaInicioAtencion'];
                                // Verificar si faltan campos requeridos
                                $this->formData($data, $requiredFields, $value, $invoice['numFactura'], $keyU + 1, $user['numDocumentoIdentificacion'], $keyH + 1, 'hospitalizacion');
                            }
                        }

                        //RECIEN NACIDOS
                        if (isset($user['servicios']['recienNacidos']) && count($user['servicios']['recienNacidos']) > 0) {
                            foreach ($user['servicios']['recienNacidos'] as $keyRN => $value) {
                                //fechaNacimiento NO ESTABA EN LA LISTA SE AGREGA POR PETICION DE GERMAN
                                $requiredFields = ['fechaNacimiento', 'tipoDocumentoIdentificacion', 'numConsultasCPrenatal'];
                                // Verificar si faltan campos requeridos
                                $this->formData($data, $requiredFields, $value, $invoice['numFactura'], $keyU + 1, $user['numDocumentoIdentificacion'], $keyRN + 1, 'recienNacidos');
                            }
                        }
                    }
                }
            }
        }

        return $data;
    }

    public function headings(): array
    {
        return [
            'num_factura',
            'id_usuario',
            'num_identificacion',
            'id_servicio',
            'servicio',
            'campo',
            'valor',
        ];
    }

    public function formData(&$array, $requiredFields, $value, $element1, $key1 = null, $element2 = null, $key2 = null, $campo = null)
    {
        foreach ($requiredFields as $field) {
            if (empty($value[$field])) {
                $array[] = [
                    $element1,
                    $key1,
                    $element2,
                    $key2,
                    $campo,
                    $field,
                    null,
                ];
            }
        }
    }
}
