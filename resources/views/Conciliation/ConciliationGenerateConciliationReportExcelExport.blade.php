<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Acta de Conciliación</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
        }
        table {
            border-collapse: collapse;
            width: 100%;
        }
        td, th {
            border: 1px solid black;
            padding: 5px;
            height: 20px;
            vertical-align: middle;
            font-size: 11px;
        }
    </style>
</head>
<body>

<table>
    <!-- Fila 1 -->
    <tr>
        <!-- A1:D3 combinadas (4 columnas x 3 filas) -->
        <td rowspan="3" colspan="4" style="border: 1px solid black;">&nbsp;</td>

        <!-- E1 vacía -->
        <td style="border: none;">&nbsp;</td>
        <!-- F1 -->
        <td>&nbsp;</td>
        <!-- G1 -->
        <td>&nbsp;</td>
        <!-- H1 -->
        <td>&nbsp;</td>
        <!-- I1 -->
        <td>&nbsp;</td>
        <!-- J1 -->
        <td>&nbsp;</td>

        <!-- K1 vacía -->
        <td></td>
        <!-- L1 -->
        <td style="border: 1px solid black;font-weight: bold;"  >Código</td>
        <!-- M1 -->
        <td style="border: 1px solid black;font-weight: bold;">GF-F-16</td>
    </tr>

    <!-- Fila 2 -->
    <tr>
        <!-- E2 vacía -->
        <td style="border: none;">&nbsp;</td>
        <!-- F2 vacía -->
        <td>&nbsp;</td>
        <!-- G2 vacía -->
        <td></td>
        <!-- H2 vacía -->
        <td></td>
        <!-- I2 -->
        <td style="border: 1px solid black;font-weight: bold;">Modalidad de Contrato</td>
        <!-- J2 -->
        <td style="border: 1px solid black;font-weight: bold;">{{ $data['modalities'] }}</td>

        <!-- K2 vacía -->
        <td></td>
        <!-- L2 -->
        <td  style="border: 1px solid black;font-weight: bold;">Version</td>
        <!-- M2 -->
        <td style="border: 1px solid black;font-weight: bold;">ACT-02</td>
    </tr>

    <!-- Fila 3 -->
    <tr>
        <!-- E3 vacía -->
        <td style="border: none;">&nbsp;</td>
        <!-- F3 vacía -->
        <td>&nbsp;</td>
        <!-- G3 vacía -->
        <td></td>
        <!-- H3 vacía -->
        <td></td>
        <!-- I3 -->
        <td  style="border: 1px solid black;font-weight: bold;">Recobros</td>
        <!-- J3 vacía -->
        <td style="border: 1px solid black;font-weight: bold;"></td>

        <!-- K3 vacía -->
        <td></td>
        <!-- L3 -->
        <td  style="border: 1px solid black;font-weight: bold;">Fecha</td>
        <!-- M3 -->
        <td style="border: 1px solid black;font-weight: bold;">2025.ene.02</td>
    </tr>

    <!-- Fila 4 -->
    <tr>
        <!-- A4 -->
        <td  style="border: 1px solid black;font-weight: bold;">Departamento</td>
        <!-- B4:D4 combinadas -->
        <td style="border: 1px solid black;font-weight: bold;" colspan="3">{{ $data['third']['departament'] ?? '' }}</td>

        <!-- E4 -->
        <td  colspan="3" style="border: 1px solid black;font-weight: bold;">Nombre Prestador de servicios (PS):</td>
        <!-- H4:J4 combinadas -->
        <td style="border: 1px solid black;font-weight: bold; text-align: center" colspan="3">{{ $data['third']['name'] ?? '' }}</td>

        <!-- K4 vacía -->
        <td></td>
        <!-- L4 -->
        <td  style="border: 1px solid black;font-weight: bold;">EAPB</td>
        <!-- M4 -->
        <td style="border: 1px solid black;font-weight: bold;">COOSALUD EPS-S</td>
    </tr>

    <!-- Fila 5 -->
    <tr>
        <!-- A5 -->
        <td  style="border: 1px solid black;font-weight: bold;">Municipio</td>
        <!-- B5:D5 combinadas -->
        <td style="border: 1px solid black;font-weight: bold;" colspan="3">{{ $data['third']['city'] ?? '' }}</td>

        <!-- E5 -->
        <td  style="border: 1px solid black;font-weight: bold;">NIT:</td>
        <!-- F5 -->
        <td style="border: 1px solid black;font-weight: bold;">{{ $data['third']['nit'] ?? '' }}</td>
        <!-- G5 vacía -->
        <td></td>
        <!-- H5 vacía -->
        <td></td>
        <!-- I5 -->
        <td  style="border: 1px solid black;font-weight: bold;">Fecha Conciliación</td>
        <!-- J5 -->
        <td style="border: 1px solid black;font-weight: bold;">{{ $data['dateConciliation'] ?? '' }}</td>

        <!-- K5 vacía -->
        <td></td>
        <!-- L5 vacía -->
        <td></td>
        <!-- M5 vacía -->
        <td></td>
    </tr>

    <!-- Fila 6: vacía -->
    <tr>
        <td></td><td></td><td></td><td></td>
        <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
        <td></td><td></td><td></td>
    </tr>

    <!-- Fila 7: encabezados -->
    <tr>
        <td style="background-color: #d3d3d3; font-weight: bold; border: 1px solid black;">Número de Factura</td>
        <td style="background-color: #d3d3d3; font-weight: bold; border: 1px solid black;">Número de Sub<br>Factura</td>
        <td style="background-color: #d3d3d3; font-weight: bold; border: 1px solid black;">Código Glosa</td>
        <td style="background-color: #d3d3d3; font-weight: bold; border: 1px solid black;">Contrato #</td>
        <td style="background-color: #d3d3d3; font-weight: bold; border: 1px solid black;">Valor Factura</td>
        <td style="background-color: #d3d3d3; font-weight: bold; border: 1px solid black;">Mes facturado</td>
        <td style="background-color: #d3d3d3; font-weight: bold; border: 1px solid black;">Departamento del<br>Afiliado</td>
        <td style="background-color: #d3d3d3; font-weight: bold; border: 1px solid black;">Valor Glosa Inicial</td>
        <td style="background-color: #d3d3d3; font-weight: bold; border: 1px solid black;">Valor pendiente por<br>conciliar</td>
        <td style="background-color: #d3d3d3; font-weight: bold; border: 1px solid black;">Valor aceptado por EPS<br>en conciliación</td>
        <td style="background-color: #d3d3d3; font-weight: bold; border: 1px solid black;">Valor aceptado por IPS<br>en conciliación</td>
        <td style="background-color: #d3d3d3; font-weight: bold; border: 1px solid black;">Valor ratificado no<br>acuerdo</td>
        <td style="background-color: #d3d3d3; font-weight: bold; border: 1px solid black;">Justificación de la Conciliación con el<br>Prestador de servicios (PS)</td>
    </tr>

    <!-- Filas de facturas -->
    @foreach($data['invoices'] as $invoice)
        <tr>
            <td style="border: 1px solid black;">{{ $invoice['invoice_number'] }}</td>
            <td style="border: 1px solid black;">{{ $invoice['sub_invoice_number'] }}</td>
            <td style="border: 1px solid black;">{{ $invoice['gloss_code'] }}</td>
            <td style="border: 1px solid black;">{{ $invoice['contract_number'] }}</td>
            <td style="border: 1px solid black;text-align: right;">{{ $invoice['total_value'] }}</td>
            <td style="border: 1px solid black;">{{ $invoice['invoiced_month'] }}</td>
            <td style="border: 1px solid black;">{{ $invoice['affiliated_department'] }}</td>
            <td style="border: 1px solid black;text-align: right;">{{ $invoice['initial_gloss_value'] }}</td>
            <td style="border: 1px solid black;text-align: right;">{{ $invoice['pending_value'] }}</td>
            <td style="border: 1px solid black;text-align: right;">{{ $invoice['accepted_value_eps'] }}</td>
            <td style="border: 1px solid black;text-align: right;">{{ $invoice['accepted_value_ips'] }}</td>
            <td style="border: 1px solid black;text-align: right;">{{ $invoice['ratified_value'] }}</td>
            <td style="border: 1px solid black;">{{ $invoice['justification'] }}</td>
        </tr>
    @endforeach

    <!-- Fila 13: Sumatorias -->
    <tr>
        <td style="border: 1px solid black; font-weight: bold; background-color: #e9e9e9;">TOTALES:</td>
        <td style="border: 1px solid black; font-weight: bold; background-color: #e9e9e9;"></td>
        <td style="border: 1px solid black; font-weight: bold; background-color: #e9e9e9;"></td>
        <td style="border: 1px solid black; font-weight: bold; background-color: #e9e9e9;"></td>
        <td style="border: 1px solid black; font-weight: bold; background-color: #e9e9e9; text-align: right;">{{ $data['totales']['total_value'] }}</td>
        <td style="border: 1px solid black; font-weight: bold; background-color: #e9e9e9;"></td>
        <td style="border: 1px solid black; font-weight: bold; background-color: #e9e9e9;"></td>
        <td style="border: 1px solid black; font-weight: bold; background-color: #e9e9e9; text-align: right;">{{ $data['totales']['initial_gloss_value'] }}</td>
        <td style="border: 1px solid black; font-weight: bold; background-color: #e9e9e9; text-align: right;">{{ $data['totales']['pending_value'] }}</td>
        <td style="border: 1px solid black; font-weight: bold; background-color: #e9e9e9; text-align: right;">{{ $data['totales']['accepted_value_eps'] }}</td>
        <td style="border: 1px solid black; font-weight: bold; background-color: #e9e9e9; text-align: right;">{{ $data['totales']['accepted_value_ips'] }}</td>
        <td style="border: 1px solid black; font-weight: bold; background-color: #e9e9e9; text-align: right;">{{ $data['totales']['ratified_value'] }}</td>
        <td style="border: 1px solid black; font-weight: bold; background-color: #e9e9e9;"></td>
    </tr>

    <!-- Fila 14: vacía -->
    <tr>
        <td></td><td></td><td></td><td></td>
        <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
        <td></td><td></td><td></td>
    </tr>

    <!-- Fila 15: vacía -->
    <tr>
        <td></td><td></td><td></td><td></td>
        <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
        <td></td><td></td><td></td>
    </tr>

    <!-- Fila 16: RESULTADO CONCILIACION -->
    <tr>
        <td style="background-color: #d3d3d3; font-weight: bold; border: 1px solid black;" colspan="3">RESULTADO CONCILIACIÓN</td>
        <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
        <td></td><td></td><td></td>
    </tr>

    <!-- Fila 17: Valor Factura -->
    <tr>
        <td style="border: 1px solid black;" colspan="2" >Valor Factura</td>
        <td style="border: 1px solid black;text-align: right;" >{{ $data['totales']['total_value'] }}</td>
        <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
        <td></td><td></td><td></td>
    </tr>

    <!-- Fila 18: Valor Glosa Inicial -->
    <tr>
        <td style="border: 1px solid black;" colspan="2">Valor Glosa Inicial</td>
        <td style="border: 1px solid black;text-align: right;">{{ $data['totales']['initial_gloss_value'] }}</td>
        <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
        <td></td><td></td><td></td>
    </tr>

    <!-- Fila 19: Valor pendiente por conciliar -->
    <tr>
        <td style="border: 1px solid black;" colspan="2">Valor pendiente por conciliar</td>
        <td style="border: 1px solid black;text-align: right;">{{ $data['totales']['pending_value'] }}</td>
        <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
        <td></td><td></td><td></td>
    </tr>

    <!-- Fila 20: Valor aceptado por EPS -->
    <tr>
        <td style="border: 1px solid black;" colspan="2">Valor aceptado por EPS en conciliación</td>
        <td style="border: 1px solid black;text-align: right;">{{ $data['totales']['accepted_value_eps'] }}</td>
        <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
        <td></td><td></td><td></td>
    </tr>

    <!-- Fila 21: Valor aceptado por IPS -->
    <tr>
        <td style="border: 1px solid black;" colspan="2">Valor aceptado por IPS en conciliación</td>
        <td style="border: 1px solid black;text-align: right;">{{ $data['totales']['accepted_value_ips'] }}</td>
        <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
        <td></td><td></td><td></td>
    </tr>

    <!-- Fila 22: Valor ratificado no acuerdo -->
    <tr>
        <td  style="border: 1px solid black;" colspan="2">Valor ratificado no acuerdo</td>
        <td style="border: 1px solid black;text-align: right;">{{ $data['totales']['ratified_value'] }}</td>
        <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
        <td></td><td></td><td></td>
    </tr>

    <!-- Fila 23: vacía -->
    <tr>
        <td></td><td></td><td></td><td></td>
        <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
        <td></td><td></td><td></td>
    </tr>

    <!-- Fila 24: texto legal -->
    <tr>
        <td style="text-align: center; height: 40px; word-wrap: break-word;">
            La presente acta se expide en la ciudad de CARTAGENA, el día {{ $data["formattedDateReport"] }} y se suscribe por los funcionarios representantes de las entidades que participan en el proceso de conciliación.
        </td>
        <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
        <td></td><td></td><td></td>
    </tr>

    <!-- Fila 25: cláusula aclaratoria -->
    <tr>
        <td style="text-align: center; height: 40px; word-wrap: break-word;">
            CLAUSULA ACLARATORIA: INTEGRALIDAD DE LA CARTERA: Los saldos dispuestos en la presente acta no constituyen una cuenta de cobro, hasta tanto sea cotejado y analizado en el marco de la integralidad de cartera entre el prestador de servicios y la EPS.
        </td>
        <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
        <td></td><td></td><td></td>
    </tr>

    <!-- Fila 26: vacía -->
    <tr>
        <td></td><td></td><td></td><td></td>
        <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
        <td></td><td></td><td></td>
    </tr>
    <tr>
        <td></td><td></td><td></td><td></td>
        <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
        <td></td><td></td><td></td>
    </tr>

    <!-- Fila 27: Firma IPS -->
    <tr>
        <td style="border-top: 1px solid black;font-weight: bold;" >Firma:</td>
        <td style="border-top: 1px solid black;font-weight: bold;"></td>
        <td style="border-top: 1px solid black;font-weight: bold;"></td>
        <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
        <td   style="border-top: 1px solid black;font-weight: bold;" >Firma:</td>
        <td  style="border-top: 1px solid black;font-weight: bold;"></td><td></td>
    </tr>

    <!-- Fila 28: Nombre IPS -->
    <tr>
        <td style="font-weight: bold;">Nombre representante de la IPS : </td>
        <td>{{$data["signatures"]["nameIPSrepresentative"]}}</td>
        <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
        <td style="font-weight: bold;">Nombre representante de la EPS : ALCIDES HERNANDEZ</td>
        <td></td><td></td>
    </tr>

    <!-- Fila 29: Cargo IPS -->
    <tr>
        <td style="font-weight: bold;">Cargo: </td>
        <td>{{$data["signatures"]["positionIPSrepresentative"]}}</td>
        <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
        <td style="font-weight: bold;">Cargo: DIRECTOR DE CUENTAS MEDICAS</td>
        <td></td><td></td>
    </tr>

    <!-- Fila 30: vacía -->
    <tr>
        <td></td><td></td><td></td><td></td>
        <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
        <td></td><td></td><td></td>
    </tr>

    <!-- Fila 31: Encabezados de tabla de firmas internas -->
    <tr>
        <td colspan="2" style="background-color: #d3d3d3; font-weight: bold; border: 1px solid black;">&nbsp;</td>
        <td colspan="3" style="background-color: #d3d3d3; font-weight: bold; border: 1px solid black;">NOMBRE</td>
        <td colspan="2" style="background-color: #d3d3d3; font-weight: bold; border: 1px solid black;">CARGO</td>
        <td colspan="2" style="background-color: #d3d3d3; font-weight: bold; border: 1px solid black;">FIRMA</td>
    </tr>

    <!-- Fila 32: Elaboro -->
    <tr>
        <td colspan="2" style="font-weight: bold; border: 1px solid black;">Elaboro (Conciliador)</td>
        <td colspan="3" style="border: 1px solid black;">{{ $data['signatures']['elaborator_full_name'] ?? '' }}</td>
        <td colspan="2" style="border: 1px solid black;">{{ $data['signatures']['elaborator_position'] ?? '' }}</td>
        <td colspan="2" style="border: 1px solid black;">&nbsp;</td>
    </tr>

    <!-- Fila 33: Reviso -->
    <tr>
        <td colspan="2" style="font-weight: bold; border: 1px solid black;">Reviso (Lider de glosas y conciliaciones)</td>
        <td colspan="3" style="border: 1px solid black;">{{ $data['signatures']['reviewer_full_name'] ?? '' }}</td>
        <td colspan="2" style="border: 1px solid black;">{{ $data['signatures']['reviewer_position'] ?? '' }}</td>
        <td colspan="2" style="border: 1px solid black;">&nbsp;</td>
    </tr>

    <!-- Fila 34: Aprobó -->
    <tr>
        <td colspan="2" style="font-weight: bold; border: 1px solid black;">Aprobó (Coordinador de glosas y Conciliaciones)</td>
        <td colspan="3" style="border: 1px solid black;">{{ $data['signatures']['approver_full_name'] ?? '' }}</td>
        <td colspan="2" style="border: 1px solid black;">{{ $data['signatures']['approver_position'] ?? '' }}</td>
        <td colspan="2" style="border: 1px solid black;">&nbsp;</td>
    </tr>

    <!-- Fila 35: Aprobó Representante -->
    <tr>
        <td colspan="2" style="font-weight: bold; border: 1px solid black;">Aprobó (Representante Legal / Director Nacional de Cuentas Medicas)</td>
        <td colspan="3" style="border: 1px solid black;">{{ $data['signatures']['legal_representative_full_name'] ?? '' }}</td>
        <td colspan="2" style="border: 1px solid black;">{{ $data['signatures']['legal_representative_position'] ?? '' }}</td>
        <td colspan="2" style="border: 1px solid black;">&nbsp;</td>
    </tr>

    <!-- Fila 36: Revisión Director Auditoría -->
    <tr>
        <td colspan="2" style="font-weight: bold; border: 1px solid black;">Revisión por Director de auditoria en Salud</td>
        <td colspan="3" style="border: 1px solid black;">{{ $data['signatures']['health_audit_director_full_name'] ?? '' }}</td>
        <td colspan="2" style="border: 1px solid black;">{{ $data['signatures']['health_audit_director_position'] ?? '' }}</td>
        <td colspan="2" style="border: 1px solid black;">&nbsp;</td>
    </tr>

    <!-- Fila 37: Revisión Vicepresidencia -->
    <tr>
        <td colspan="2" style="font-weight: bold; border: 1px solid black;">Revisión por Vicepresidencia de Planeación y Control Financiero</td>
        <td colspan="3" style="border: 1px solid black;">{{ $data['signatures']['vp_planning_control_full_name'] ?? '' }}</td>
        <td colspan="2" style="border: 1px solid black;">{{ $data['signatures']['vp_planning_control_position'] ?? '' }}</td>
        <td colspan="2" style="border: 1px solid black;">&nbsp;</td>
    </tr>

</table>

</body>
</html>
