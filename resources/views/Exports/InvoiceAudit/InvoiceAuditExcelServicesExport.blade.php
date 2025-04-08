<table>
    <thead>
        <tr>
            {{-- <td>Paquete</td> --}}
            <td>Tercero</td>
            <td>Factura</td>
            <td>Cedula</td>
            <td>Nombre</td>

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
                {{-- <td> {{ $item["invoice_audit"]["assignment"]["assignmentBatche"]["name"] }}</td> --}}

                <td> {{ $item["invoice_audit"]["third"]["name"] }}</td>

                <td> {{ $item["invoice_audit"]["invoice_number"] }}</td>


                <td> {{ $item['patient']["identification_number"] }}</td>
                <td> {{ $item['patient']["first_name"] }}</td>

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
