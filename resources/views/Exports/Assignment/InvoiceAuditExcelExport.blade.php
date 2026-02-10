<table>
    <thead>
        <tr>
            <td>Id</td>
            <td>Número De Factura</td>
            <td>Valor Total</td>
            <td>Origen</td>
            <td>expedition_date</td>
            <td>date_entry</td>
            <td>date_departure</td>
            <td>modality</td>
            <td>regimen</td>
            <td>coverage</td>
            <td>contract_number</td>
        </tr>
    </thead>
    <tbody>
        @foreach ($data as $item)
            <tr>
                <td> {{ $item['id'] }}</td>
                <td> {{ $item['invoice_number'] }}</td>
                <td> {{ $item['total_value'] }}</td>
                <td> {{ $item['origin'] }}</td>
                <td> {{ $item['expedition_date'] }}</td>
                <td> {{ $item['date_entry'] }}</td>
                <td> {{ $item['date_departure'] }}</td>
                <td> {{ $item['modality'] }}</td>
                <td> {{ $item['regimen'] }}</td>
                <td> {{ $item['coverage'] }}</td>
                <td> {{ $item['contract_number'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
