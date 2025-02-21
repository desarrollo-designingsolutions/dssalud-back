<table>
    <thead>
        <tr>
            <td>Proceso</td>
            <td>Tipo de Validación</td>
            <td>Num Factura</td>
            <td>Archivo</td>
            <td>Columna</td>
            <td>Fila</td>
            <td>Validacion</td>
            <td>Dato registrado</td>
            <td>Descripción error</td>
        </tr>
    </thead>
    <tbody>
        @foreach ($data as $item)
        <tr>
                <td> {{ $item['type'] }}</td>
                <td> {{ $item['validacion_type_Y'] }}</td>
                <td> {{ $item['num_invoice'] }}</td>
                <td> {{ $item['file'] }}</td>
                <td> {{ $item['column'] }}</td>
                <td> {{ $item['row'] }}</td>
                <td> {{ $item['validacion'] }}</td>
                <td> {{ $item['data'] }}</td>
                <td> {{ $item['error'] }}</td>
            </tr>
            @endforeach
    </tbody>
</table>
