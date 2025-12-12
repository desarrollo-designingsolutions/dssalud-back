<div>
    <table>
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Nit</th>
                <th>Tercero</th>
                <th>Link</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data as $row)
                <tr class="">
                    <td>{{ $row['name'] }}</td>
                    <td>{{ $row['third_nit'] }}</td>
                    <td>{{ $row['third_name'] }}</td>
                    <td>{{ $row['link'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
