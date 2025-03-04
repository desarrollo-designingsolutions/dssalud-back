<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
</head>

<body>

    <div class="table-responsive">
        <table class="table table-primary" border="1">
            <thead>
                <tr>
                    <th>Archivo</th>
                    <th>Dato Regristrado</th>
                    <th>Descripción de error</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['infoValidation']['errorMessages'] as $error)
                    <tr class="">
                        <td>{{ $error['file'] }}</td>
                        <td>{{ $error['data'] }}</td>
                        <td>{{ $error['error'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</body>

</html>
