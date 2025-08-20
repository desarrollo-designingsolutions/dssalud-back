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
        .bold {
            font-weight: bold;
        }
        .center {
            text-align: center;
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
        .no-border {
            border: none;
        }
        .total {
            font-weight: bold;
            background-color: #e9e9e9;
        }
        .right {
            text-align: right;
        }
        .header-gray {
            background-color: #d3d3d3;
            font-weight: bold;
        }
    </style>
</head>
<body>

<table>
    <!-- Fila 1 -->
    <tr>
        <!-- A1:D3 combinadas (4 columnas x 3 filas) -->
        <td rowspan="3" colspan="4" class="center" style="height: 60px;">&nbsp;</td>

        <!-- E1 vacía -->
        <td class="no-border">&nbsp;</td>
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
        <td class="bold">Código</td>
        <!-- M1 -->
        <td>GF-F-16</td>
    </tr>

    <!-- Fila 2 -->
    <tr>
        <!-- E2 vacía -->
        <td class="no-border">&nbsp;</td>
        <!-- F2 vacía -->
        <td>&nbsp;</td>
        <!-- G2 vacía -->
        <td></td>
        <!-- H2 vacía -->
        <td></td>
        <!-- I2 -->
        <td>Modalidad de Contrato</td>
        <!-- J2 -->
        <td>{{ $data['modalities'] }}</td>

        <!-- K2 vacía -->
        <td></td>
        <!-- L2 -->
        <td class="bold">Version</td>
        <!-- M2 -->
        <td>ACT-02</td>
    </tr>

    <!-- Fila 3 -->
    <tr>
        <!-- E3 vacía -->
        <td class="no-border">&nbsp;</td>
        <!-- F3 vacía -->
        <td>&nbsp;</td>
        <!-- G3 vacía -->
        <td></td>
        <!-- H3 vacía -->
        <td></td>
        <!-- I3 -->
        <td class="bold">Recobros</td>
        <!-- J3 vacía -->
        <td></td>

        <!-- K3 vacía -->
        <td></td>
        <!-- L3 -->
        <td class="bold">Fecha</td>
        <!-- M3 -->
        <td>2025.ene.02</td>
    </tr>

    <!-- Fila 4 -->
    <tr>
        <!-- A4 -->
        <td class="bold">Departamento</td>
        <!-- B4:D4 combinadas -->
        <td colspan="3">{{ $data['third']['departament'] ?? '' }}</td>

        <!-- E4 -->
        <td class="bold">Nombre Prestador de servicios (PS):</td>
        <!-- F4 vacía -->
        <td></td>
        <!-- G4 vacía -->
        <td></td>
        <!-- H4:J4 combinadas -->
        <td colspan="3">{{ $data['third']['name'] ?? '' }}</td>

        <!-- K4 vacía -->
        <td></td>
        <!-- L4 -->
        <td class="bold">EAPB</td>
        <!-- M4 -->
        <td>COOSALUD EPS-S</td>
    </tr>

    <!-- Fila 5 -->
    <tr>
        <!-- A5 -->
        <td class="bold">Municipio</td>
        <!-- B5:D5 combinadas -->
        <td colspan="3">{{ $data['third']['city'] ?? '' }}</td>

        <!-- E5 -->
        <td class="bold">NIT:</td>
        <!-- F5 -->
        <td>{{ $data['third']['nit'] ?? '' }}</td>
        <!-- G5 vacía -->
        <td></td>
        <!-- H5 vacía -->
        <td></td>
        <!-- I5 -->
        <td class="bold">Fecha Conciliación</td>
        <!-- J5 -->
        <td>{{ $data['dateConciliation'] ?? '' }}</td>

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
        <td class="bold">Número de Factura</td>
        <td class="bold">Número de Sub<br>Factura</td>
        <td class="bold">Código Glosa</td>
        <td class="bold">Contrato #</td>
        <td class="bold">Valor Factura</td>
        <td class="bold">Mes facturado</td>
        <td class="bold">Departamento del<br>Afiliado</td>
        <td class="bold">Valor Glosa Inicial</td>
        <td class="bold">Valor pendiente por<br>conciliar</td>
        <td class="bold">Valor aceptado por EPS<br>en conciliación</td>
        <td class="bold">Valor aceptado por IPS<br>en conciliación</td>
        <td class="bold">Valor ratificado no<br>acuerdo</td>
        <td class="bold">Justificación de la Conciliación con el<br>Prestador de servicios (PS)</td>
    </tr>

    <!-- Filas de facturas -->
    @foreach($data['invoices'] as $invoice)
        <tr>
            <td>{{ $invoice['invoice_number'] }}</td>
            <td>{{ $invoice['sub_invoice_number'] }}</td>
            <td>{{ $invoice['gloss_code'] }}</td>
            <td>{{ $invoice['contract_number'] }}</td>
            <td class="right">{{ $invoice['total_value'] }}</td>
            <td>{{ $invoice['invoiced_month'] }}</td>
            <td>{{ $invoice['affiliated_department'] }}</td>
            <td class="right">{{ $invoice['initial_gloss_value'] }}</td>
            <td class="right">{{ $invoice['pending_value'] }}</td>
            <td class="right">{{ $invoice['accepted_value_eps'] }}</td>
            <td class="right">{{ $invoice['accepted_value_ips'] }}</td>
            <td class="right">{{ $invoice['ratified_value'] }}</td>
            <td>{{ $invoice['justification'] }}</td>
        </tr>
    @endforeach

    <!-- Fila 13: Sumatorias -->
    <tr>
        <td class="total">TOTALES:</td>
        <td class="total"></td>
        <td class="total"></td>
        <td class="total"></td>
        <td class="total right">{{ $data['totales']['total_value'] }}</td>
        <td class="total"></td>
        <td class="total"></td>
        <td class="total right">{{ $data['totales']['initial_gloss_value'] }}</td>
        <td class="total right">{{ $data['totales']['pending_value'] }}</td>
        <td class="total right">{{ $data['totales']['accepted_value_eps'] }}</td>
        <td class="total right">{{ $data['totales']['accepted_value_ips'] }}</td>
        <td class="total right">{{ $data['totales']['ratified_value'] }}</td>
        <td class="total"></td>
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
        <td class="bold" colspan="3">RESULTADO CONCILIACION</td>
        <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
        <td></td><td></td><td></td>
    </tr>

    <!-- Fila 17: Valor Factura -->
    <tr>
        <td class="bold">Valor Factura</td>
        <td></td>
        <td class="right">{{ $data['totales']['total_value'] }}</td>
        <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
        <td></td><td></td><td></td>
    </tr>

    <!-- Fila 18: Valor Glosa Inicial -->
    <tr>
        <td class="bold">Valor Glosa Inicial</td>
        <td></td>
        <td class="right">{{ $data['totales']['initial_gloss_value'] }}</td>
        <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
        <td></td><td></td><td></td>
    </tr>

    <!-- Fila 19: Valor pendiente por conciliar -->
    <tr>
        <td class="bold">Valor pendiente por conciliar</td>
        <td></td>
        <td class="right">{{ $data['totales']['pending_value'] }}</td>
        <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
        <td></td><td></td><td></td>
    </tr>

    <!-- Fila 20: Valor aceptado por EPS -->
    <tr>
        <td class="bold">Valor aceptado por EPS en conciliación</td>
        <td></td>
        <td class="right">{{ $data['totales']['accepted_value_eps'] }}</td>
        <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
        <td></td><td></td><td></td>
    </tr>

    <!-- Fila 21: Valor aceptado por IPS -->
    <tr>
        <td class="bold">Valor aceptado por IPS en conciliación</td>
        <td></td>
        <td class="right">{{ $data['totales']['accepted_value_ips'] }}</td>
        <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
        <td></td><td></td><td></td>
    </tr>

    <!-- Fila 22: Valor ratificado no acuerdo -->
    <tr>
        <td class="bold">Valor ratificado no acuerdo</td>
        <td></td>
        <td class="right">{{ $data['totales']['ratified_value'] }}</td>
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
    <td class="center" style="height: 40px; word-wrap: break-word;">
        La presente acta se expide en la ciudad de CARTAGENA, el día {{ $data["formattedDateReport"] }} y se suscribe por los funcionarios representantes de las entidades que participan en el proceso de conciliación.
    </td>
    <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
    <td></td><td></td><td></td>
</tr>

<!-- Fila 25: cláusula aclaratoria -->
<tr>
    <td class="center" style="height: 40px; word-wrap: break-word;">
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

    <!-- Fila 27: Firma IPS -->
    <tr>
        <td class="bold">Firma:</td>
        <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
        <td class="bold">Firma:</td>
        <td></td><td></td>
    </tr>

    <!-- Fila 28: Nombre IPS -->
    <tr>
        <td class="bold">Nombre representante de la IPS : </td>
        <td>{{$data["signatures"]["nameIPSrepresentative"]}}</td>
        <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
        <td class="bold">Nombre representante de la EPS : ALCIDES HERNANDEZ</td>
        <td></td><td></td>
    </tr>

    <!-- Fila 29: Cargo IPS -->
    <tr>
        <td class="bold">Cargo: </td>
        <td>{{$data["signatures"]["positionIPSrepresentative"]}}</td>
        <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
        <td class="bold">Cargo: DIRECTOR DE CUENTAS MEDICAS</td>
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
        <td class="header-gray" colspan="2"></td>
        <td class="header-gray" colspan="3">NOMBRE</td>
        <td class="header-gray" colspan="2">CARGO</td>
        <td class="header-gray" colspan="2">FIRMA</td>
        <td></td><td></td><td></td><td></td><td></td><td></td>
    </tr>

    <!-- Fila 32: Elaboro -->
    <tr>
        <td class="bold">Elaboro (Conciliador)</td>
        <td></td>
        <td>{{ $data['signatures']['elaborator_full_name'] ?? '' }}</td>
        <td></td><td></td>
        <td>{{ $data['signatures']['elaborator_position'] ?? '' }}</td>
        <td></td>
        <td></td><td></td>
        <td></td><td></td><td></td><td></td><td></td><td></td>
    </tr>

    <!-- Fila 33: Reviso -->
    <tr>
        <td class="bold">Reviso (Lider de glosas y conciliaciones)</td>
        <td></td>
        <td>{{ $data['signatures']['reviewer_full_name'] ?? '' }}</td>
        <td></td><td></td>
        <td>{{ $data['signatures']['reviewer_position'] ?? '' }}</td>
        <td></td>
        <td></td><td></td>
        <td></td><td></td><td></td><td></td><td></td><td></td>
    </tr>

    <!-- Fila 34: Aprobó -->
    <tr>
        <td class="bold">Aprobó (Coordinador de glosas y Conciliaciones)</td>
        <td></td>
        <td>{{ $data['signatures']['approver_full_name'] ?? '' }}</td>
        <td></td><td></td>
        <td>{{ $data['signatures']['approver_position'] ?? '' }}</td>
        <td></td>
        <td></td><td></td>
        <td></td><td></td><td></td><td></td><td></td><td></td>
    </tr>

    <!-- Fila 35: Aprobó Representante -->
    <tr>
        <td class="bold">Aprobó (Representante Legal / Director Nacional de Cuentas Medicas)</td>
        <td></td>
        <td>{{ $data['signatures']['legal_representative_full_name'] ?? '' }}</td>
        <td></td><td></td>
        <td>{{ $data['signatures']['legal_representative_position'] ?? '' }}</td>
        <td></td>
        <td></td><td></td>
        <td></td><td></td><td></td><td></td><td></td><td></td>
    </tr>

    <!-- Fila 36: Revisión Director Auditoría -->
    <tr>
        <td class="bold">Revisión por Director de auditoria en Salud</td>
        <td></td>
        <td>{{ $data['signatures']['health_audit_director_full_name'] ?? '' }}</td>
        <td></td><td></td>
        <td>{{ $data['signatures']['health_audit_director_position'] ?? '' }}</td>
        <td></td>
        <td></td><td></td>
        <td></td><td></td><td></td><td></td><td></td><td></td>
    </tr>

    <!-- Fila 37: Revisión Vicepresidencia -->
    <tr>
        <td class="bold">Revisión por Vicepresidencia de Planeación y Control Financiero</td>
        <td></td>
        <td>{{ $data['signatures']['vp_planning_control_full_name'] ?? '' }}</td>
        <td></td><td></td>
        <td>{{ $data['signatures']['vp_planning_control_position'] ?? '' }}</td>
        <td></td>
        <td></td><td></td>
        <td></td><td></td><td></td><td></td><td></td><td></td>
    </tr>

</table>

</body>
</html>
