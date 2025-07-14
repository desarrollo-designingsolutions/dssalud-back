<table>
    <thead>
        <tr>
            <td>Título</td>
            <td>Estado de la respuesta</td>
            <td>Fecha de la respuesta</td>
            <td>Tercero</td>
            <td>Asignado</td>
            <td>Grupo de conciliación</td>
        </tr>
    </thead>
    <tbody>
        @foreach ($data as $item)
            <tr>
                <td> {{ $item['title'] }}</td>
                <td style="background: {{ $item['response_status_backgroundColor'] }} }}">
                    {{ $item['response_status_description'] }}</td>
                <td> {{ $item['response_date'] }}</td>
                <td> {{ $item['third_name'] }}</td>
                <td> {{ $item['user_name'] }}</td>
                <td> {{ $item['reconciliation_group_name'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
