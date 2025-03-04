<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
</head>

<body>
    {{-- <p>{{ $data["infoValidation"]['totalInvoices'] }}</p>
    <p>{{ $data["infoValidation"]['jsonSuccessfullInvoices'] }}</p>
    <p>{{ $data["infoValidation"]['successfulInvoices'] }}</p>
    <p>{{ $data["infoValidation"]['totalSuccessfulInvoices'] }}</p>
    <p>{{ $data["infoValidation"]['failedInvoices'] }}</p>
    <p>{{ $data["infoValidation"]['totalFailedInvoices'] }}</p>
    <p>{{ $data["infoValidation"]['invoicesWithErrors'] }}</p>
    <p>{{ $data["infoValidation"]['invoicesWithoutErrors'] }}</p>
    <p>{{ $data["infoValidation"]['totalInvoicesWithErrors'] }}</p>
    <p>{{ $data["infoValidation"]['totalInvoicesWithoutErrors'] }}</p>
    <p>{{ $data["infoValidation"]['errorMessages'] }}</p> --}}


    <div class="table-responsive">
        <table class="table table-primary" border="1">
            <thead>
                <tr>
                    <th>Archivo</th>
                    <th>Columna</th>
                    <th>Fila</th>
                    <th>Validación</th>
                    <th>Dato Regristrado</th>
                    <th>Descripción de error</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['infoValidation']['errorMessages'] as $error)
                    <tr class="">
                        <td>{{ $error['file'] }}</td>
                        <td>{{ $error['column'] }}</td>
                        <td>{{ $error['row'] }}</td>
                        <td>{{ $error['validacion'] }}</td>
                        <td>{{ $error['data'] }}</td>
                        <td>{{ $error['error'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</body>

</html>
