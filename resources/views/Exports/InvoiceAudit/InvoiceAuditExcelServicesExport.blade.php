<table>
    <thead>
        <tr>

            @if (isset($request['from']) && $request['from'] == 'thirds')
                <td>Tercero</td>
            @endif

            @if (isset($request['from']) && ($request['from'] == 'invoices' || $request['from'] == 'thirds'))
                <td>Factura</td>
            @endif

            @if (isset($request['from']) &&  ($request['from'] == 'patients' || $request['from'] == 'invoices' || $request['from'] == 'thirds'))
                <td>Cedula</td>
                <td>Nombre</td>
            @endif

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

                @if (isset($request['from']) && $request['from'] == 'thirds')
                    <td> {{ $item['invoice_audit']['third']['name'] }}</td>
                @endif

                @if (isset($request['from']) && ($request['from'] == 'invoices' || $request['from'] == 'thirds'))
                    <td> {{ $item['invoice_audit']['invoice_number'] }}</td>
                @endif

                @if (isset($request['from']) &&  ($request['from'] == 'patients' || $request['from'] == 'invoices' || $request['from'] == 'thirds'))
                    <td> {{ $item['patient']['identification_number'] }}</td>
                    <td> {{ $item['patient']['first_name'] }}</td>
                @endif

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
