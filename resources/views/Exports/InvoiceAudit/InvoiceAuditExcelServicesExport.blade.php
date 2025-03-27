<table>
    <thead>
        <tr>
            <td>id</td>
            <td>invoice_audit_id</td>
            <td>patient_id</td>
            <td>detail_code</td>
            <td>description</td>
            <td>quantity</td>
            <td>unit_value</td>
            <td>total_value</td>
        </tr>
    </thead>
    <tbody>
        @foreach ($data as $item)
            <tr>
                <td> {{ $item['id'] }}</td>
                <td> {{ $item['invoice_audit_id'] }}</td>
                <td> {{ $item['patient_id'] }}</td>
                <td> {{ $item['detail_code'] }}</td>
                <td> {{ $item['description'] }}</td>
                <td> {{ $item['quantity'] }}</td>
                <td> {{ $item['unit_value'] }}</td>
                <td> {{ $item['total_value'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
