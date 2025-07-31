<div>
    <table>
        <thead>
            <tr>
                <th> Número de factura</th>
                <th>Total</th>
                <th>Origen</th>
                <th>Modalidad</th>
                <th>Número de contacto</th>
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
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
