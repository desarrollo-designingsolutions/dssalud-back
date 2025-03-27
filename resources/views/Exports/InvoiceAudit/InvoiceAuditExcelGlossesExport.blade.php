<table>
    <thead>
        <tr>
            <td>id</td>
            <td>general_code_glosa_id</td>
            <td>code</td>
            <td>description</td>
        </tr>
    </thead>
    <tbody>
        @foreach ($data as $item)
            <tr>
                <td>{{ $item['id'] }}</td>
                <td>{{ $item['general_code_glosa_id'] }}</td>
                <td>{{ $item['code'] }}</td>
                <td>{{ $item['description'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
