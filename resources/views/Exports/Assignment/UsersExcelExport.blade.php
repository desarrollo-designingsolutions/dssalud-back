<table>
    <thead>
        <tr>
            <td>usuario_id</td>
            <td>nombre</td>
        </tr>
    </thead>
    <tbody>
        @foreach ($data as $item)
            <tr>
                <td> {{ $item['id'] }}</td>
                <td> {{ $item['name'].' '.$item['surname'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
