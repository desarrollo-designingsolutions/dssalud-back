<table>
    <thead>
        <tr>
            <td>usuario_id</td>
        </tr>
    </thead>
    <tbody>
        @foreach ($data as $item)
            <tr>
                <td> {{ $item['id'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
