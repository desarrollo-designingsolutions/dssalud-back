<table>
    <thead>
        <tr>
            <td>Proceso</td>
            <td>Archivo</td>
            <td>Fila</td>
            <td>Descripción error</td>
        </tr>
    </thead>
    <tbody>
        @foreach ($data as $item)
            <tr>
                <td> {{ $item['type'] }}</td>
                <td> {{ $item['file'] }}</td>
                <td> {{ $item['row'] }}</td>
                <td> {{ $item['error'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
