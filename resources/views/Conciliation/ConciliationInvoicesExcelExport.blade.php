<div>
    <table>
        <thead>
            <tr>
                <th> Número de factura</th>
                <th>Total</th>
                <th>Origen</th>
                <th>Modalidad</th>
                <th>Número de contacto</th>
                <th>Estado</th>
                <th>Suma valor ips</th>
                <th>Suma valor eps</th>
                <th>Suma valor eps ratificado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data as $row)
                <tr class="">
                    <td>{{ $row['invoice_number'] }}</td>
                    <td>{{ $row['total_value'] }}</td>
                    <td>{{ $row['origin'] }}</td>
                    <td>{{ $row['modality'] }}</td>
                    <td>{{ $row['contract_number'] }}</td>
                    {{-- <td>{{ $row['status_description'] }}</td>
                    <td>{{ $row['sum_accepted_value_ips'] }}</td>
                    <td>{{ $row['sum_accepted_value_eps'] }}</td>
                    <td>{{ $row['sum_eps_ratified_value'] }}</td> --}}


                </tr>
            @endforeach
        </tbody>
    </table>
</div>
