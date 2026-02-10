<table>
    <thead>
        <tr>
            <td>Columna</td>
            <td>Fila</td>
            <td>Valor</td>
            <td>Descripción error</td>
        </tr>
    </thead>
    <tbody>
        @foreach ($data as $item)
            <tr>
                <td> {{ $item['column'] }}</td>
                <td> {{ $item['row'] }}</td>
                <td> {{ $item['value'] }}</td>
                <td> {{ $item['errors'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
