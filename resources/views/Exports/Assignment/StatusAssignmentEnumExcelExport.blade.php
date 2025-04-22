<table>
    <thead>
        <tr>
            <td>Id</td>
            <td>Descripción</td>
        </tr>
    </thead>
    <tbody>
        @foreach ($data as $item)
            <tr>
                <td> {{ $item['value'] }}</td>
                <td> {{ $item['description'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
