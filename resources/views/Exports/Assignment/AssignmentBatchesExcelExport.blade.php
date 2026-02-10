<table>
    <thead>
        <tr>
            <td>Id</td>
            <td>Descripcion</td>
            <td>Estado</td>
            <td>Fecha de Finalizacion</td>
        </tr>
    </thead>
    <tbody>
        @foreach ($data as $item)
            <tr>
                <td>{{ $item['id'] }}</td>
                <td>{{ $item['description'] }}</td>
                <td>{{ $item['status'] }}</td>
                <td>{{ $item['due_date'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
