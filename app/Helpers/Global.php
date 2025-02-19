<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

function filterComponent($query, $request, $model = null)
{
    if (isset($request["searchQuery"]) && is_string($request["searchQuery"])) {
        $request["searchQuery"] = json_decode($request["searchQuery"], 1);
        // var_dump($request["searchQuery"]);
    }

    // Aplicar búsqueda global si existe el término de búsqueda
    if (!empty($request["searchQuery"]['generalSearch'])) {
        $relations = $request["searchQuery"]['relationsGeneral'] ?? [];
        $query->search($request["searchQuery"]['generalSearch'], $relations);
    }


    $query->where(function ($query) use ($request, $model) {
        if (isset($request['searchQuery']['arrayFilter']) && count($request['searchQuery']['arrayFilter']) > 0) {
            foreach ($request['searchQuery']['arrayFilter'] as $value) {
                if (isset($value['custom_search']) && $value['custom_search']) {
                    continue;
                }

                //Si existe el elemento relacion y es un string debo pasarlo a array
                if (isset($value['relation']) && is_string($value['relation'])) {
                    $value['relation'] = [$value['relation']];
                }

                //Busquedas si tiene relacion o no
                if (isset($value['type']) && !empty($value['type']) && $value['type'] == 'has' && isset($value['relation']) && !empty($value['relation'])) {

                    foreach ($value['relation'] as $key => $relation) {

                        $findRelation = $relation; //relaciona  buscar
                        if ((strpos($relation, '.') !== false)) { // si la relacion o palabra tiene "."
                            $findRelation = explode(".", $relation);
                            $findRelation = $findRelation[0]; // debo obtener el primer valor y solo este se busca en la class o modelo
                        }
                        //si se pasa el modelo, la relacion debe existir en el modelo, pero si no se pasa el modelo se entiende que es sobre el modelo, de donde se usa esta funcion
                        if ((!empty($model) && method_exists($model, $findRelation)) || is_null($model)) { //busco la relacion en mi modelo
                            if ($value['search'] === 1 || $value['search'] === '1') {
                                $query->has($relation);
                            } elseif ($value['search'] === 0 || $value['search'] === '0') {
                                $query->doesntHave($relation);
                            }
                        }
                    }
                }

                //Busqueda normal
                if (!empty($value['input_type']) && isset($value['search']) && !empty($value['search_key'])) {

                    if ($value['input_type'] == 'date') {
                        $query->whereDate($value['search_key'], $value['search']);
                    } elseif ($value['input_type'] == 'dateRange') {
                        $dates = explode(' to ', $value['search']);
                        $query->whereDate($value['search_key'], '>=', $dates[0])->whereDate($value['search_key'], '<=', $dates[1]);
                    } else {
                        $search = $value['search'];

                        if ($value['type'] == 'LIKE' && !is_array($search)) {
                            $search = '%' . $value['search'] . '%';
                        }
                        if (isset($value['relation'])) {
                            foreach ($value['relation'] as $key => $relation) {
                                $findRelation = $relation; //relaciona  buscar
                                if ((strpos($relation, '.') !== false)) { // si la relacion o palabra tiene "."
                                    $findRelation = explode(".", $relation);
                                    $findRelation = $findRelation[0]; // debo obtener el primer valor y solo este se busca en la class o modelo
                                }

                                //si se pasa el modelo, la relacion debe existir en el modelo, pero si no se pasa el modelo se entiende que es sobre el modelo, de donde se usa esta funcion
                                if ((!empty($model) && method_exists($model, $findRelation)) || is_null($model)) { //busco la relacion en mi modelo
                                    $query->whereHas($relation, function ($x) use ($value, $search) {
                                        if (is_array($search)) {
                                            // Verificar si es un array de objetos con clave "value"
                                            if (isset($search[0]['value'])) {
                                                $search = collect($search)->pluck('value')->toArray();
                                            }
                                            $x->whereIn($value['relation_key'], $search);
                                        } else {
                                            // Maneja el caso del valor cero
                                            if ($search === 0 || $search === '0' || !empty($search)) {
                                                $x->where($value['relation_key'], $value['type'], $search);
                                            }
                                        }
                                    });
                                }
                            }
                        } else {
                            if (is_array($search)) {
                                // Verificar si es un array de objetos con clave "value"
                                if (isset($search[0]['value'])) {
                                    $search = collect($search)->pluck('value')->toArray();
                                }
                                // Maneja el caso de valores múltiples con whereIn
                                $query->whereIn($value['search_key'], $search);
                            } else {
                                // Maneja el caso del valor cero
                                if ($search === 0 || $search === '0' || !empty($search)) {
                                    $query->where($value['search_key'], $value['type'], $search);
                                }
                            }
                        }
                    }
                }
            }
        }
    });
}

function logMessage($message)
{
    Log::info($message);
}

function paginatePerzonalized($data)
{
    $average = collect($data);

    $tamanoPagina = request('perPage', 20); // El número de elementos por página
    $pagina = request('page', 1); // Obtén el número de página de la solicitud

    // Divide la colección en segmentos de acuerdo al tamaño de la página
    $segmentos = array_chunk($average->toArray(), $tamanoPagina);

    // Obtén el segmento correspondiente a la página actual
    $segmentoActual = array_slice($segmentos, $pagina - 1, 1);

    // Convierte el segmento de nuevo a una colección
    $paginado = collect([]);
    if (isset($segmentoActual[0])) {
        $paginado = collect($segmentoActual[0]);
    }

    // Crea una instancia de la clase LengthAwarePaginator
    return $paginate = new \Illuminate\Pagination\LengthAwarePaginator(
        $paginado,
        count($average),
        $tamanoPagina,
        $pagina,
        ['path' => url()->current()]
    );
}


function clearCacheLaravel()
{
    // Limpia la caché de permisos
    Artisan::call('cache:clear');
    Artisan::call('config:cache');
    Artisan::call('view:clear');
    Artisan::call('optimize:clear');
}


function generatePastelColor($opacity = 1.0)
{
    $red = mt_rand(100, 255);
    $green = mt_rand(100, 255);
    $blue = mt_rand(100, 255);

    // Asegúrate de que la opacidad esté en el rango adecuado (0 a 1)
    $opacity = max(0, min(1, $opacity));

    return sprintf('rgba(%d, %d, %d, %.2f)', $red, $green, $blue, $opacity);
}

function truncate_text($text, $maxLength = 15)
{
    if (strlen($text) > $maxLength) {
        return substr($text, 0, $maxLength) . '...';
    }

    return $text;
}

function formatNumber($number)
{
    // Asegúrate de que el número es un número flotante
    $formattedNumber = number_format((float) $number, 2, ',', '.');

    return $formattedNumber;
}


function formattedElement($element)
{
    // Convertir el valor en función de su contenido
    switch ($element) {
        case 'null':
            $element = null;
            break;
        case 'true':
            $element = true;
            break;
        case 'false':
            $element = false;
            break;
        default:
            // No es necesario hacer nada si el valor no coincide
            break;
    }

    return $element;
}

function getStatus($value = null, $types = [], $compareByKey = 'value', $returnByKey = 'title', $typeSearch = '===')
{
    if ($value !== null) {
        // Convierte el valor a minúsculas para comparación insensible
        $inputValue = strtolower($value);

        foreach ($types as $state) {
            // Convierte el valor de la base a minúsculas para comparación insensible
            $stateValue = strtolower($state[$compareByKey]);

            // Compara de acuerdo con el tipo de búsqueda
            switch ($typeSearch) {
                case '===': // Compara estrictamente
                    if ($stateValue === $inputValue) {
                        return $state[$returnByKey];
                    }
                    break;

                case '==': // Compara con igualdad
                    if ($stateValue == $inputValue) {
                        return $state[$returnByKey];
                    }
                    break;

                case '!=': // Compara desigualdad
                    if ($stateValue != $inputValue) {
                        return $state[$returnByKey];
                    }
                    break;

                case 'LIKE': // Compara con LIKE (útil para búsquedas parciales)
                    if (strpos($stateValue, $inputValue) !== false) {
                        return $state[$returnByKey];
                    }
                    break;

                default:
                    // Si el tipo de comparación no es reconocido, usa estrictamente
                    if ($stateValue === $inputValue) {
                        return $state[$returnByKey];
                    }
                    break;
            }
        }
    }

    return $types; // Retornar el array si no se encuentra el valor
}

// Función de comparación personalizada
function customSort($a, $b, $sortingRules)
{
    foreach ($sortingRules as $rule) {
        $field = $rule['field'];

        if (!isset($a[$field]) || !isset($b[$field])) {
            // Verificar si el campo existe en ambos elementos para evitar errores
            continue;
        }

        $valueA = $a[$field];
        $valueB = $b[$field];

        // Comparar valores según el tipo de dato
        switch ($rule['type']) {
            case 'asc':
                if ($valueA != $valueB) {
                    $comparisonResult = customCompare($valueA, $valueB);
                    if ($comparisonResult !== 0) {
                        return $comparisonResult;
                    }
                }
                break;

            case 'desc':
                if ($valueA != $valueB) {
                    $comparisonResult = customCompare($valueB, $valueA);
                    if ($comparisonResult !== 0) {
                        return $comparisonResult;
                    }
                }
                break;

            // Puedes agregar más tipos de ordenación según sea necesario

            default:
                // Si el tipo de orden no es 'asc' ni 'desc', no realizar ninguna comparación
                break;
        }
    }

    return 0;
}
// Función auxiliar para realizar la comparación de valores según el tipo de dato
function customCompare($valueA, $valueB)
{
    if (is_numeric($valueA) && is_numeric($valueB)) {
        return $valueA - $valueB;  // Comparación numérica para valores numéricos
    } elseif (is_string($valueA) && is_string($valueB)) {
        return strcmp($valueA, $valueB);  // Comparación de cadenas para valores de texto
    } elseif (strtotime($valueA) !== false && strtotime($valueB) !== false) {
        // Comparación de fechas si ambos valores son fechas válidas
        return strtotime($valueA) - strtotime($valueB);
    } else {
        // Otros tipos de datos o comparaciones personalizadas según sea necesario
        // Puedes agregar más lógica aquí según los tipos de datos que esperas manejar
        return 0;
    }
}



function getStatusRips($value = null, $compareByKey = 'value', $returnByKey = 'title', $typeSearch = '===')
{
    $types = [
        ['value' => "Incomplete", 'title' => 'Incompleto'],
        ['value' => "Completed_for_us", 'title' => 'Completado por nosotros'],
        ['value' => "Completed_for_them", 'title' => 'Completado por ellos'],
        ['value' => "Not Sent", 'title' => 'Sin Enviar'],
        ['value' => "In process", 'title' => 'En proceso'],
        ['value' => "Zip Error", 'title' => 'Error Zip'],
        ['value' => "Excel Error", 'title' => 'Error Excel'],
        ['value' => "Processed", 'title' => 'Procesado'],
        ['value' => "Not Validated", 'title' => 'Sin Validar'],
        ['value' => "Pending XML", 'title' => 'Pendiente de XML'],
        ['value' => "Pending Excel", 'title' => 'Pendiente de EXCEL'],
        ['value' => "Validated", 'title' => 'Validado'],
        ['value' => "Nit Error", 'title' => 'Error Nit'],
    ];

    return getStatus($value, $types, $compareByKey, $returnByKey, $typeSearch);
}
function getTypeRips($value = null, $compareByKey = 'value', $returnByKey = 'title', $typeSearch = '===')
{
    $types = [
        ['value' => "Automatic", 'title' => 'Automatico'],
        ['value' => "Manual", 'title' => 'Manual'],
    ];

    return getStatus($value, $types, $compareByKey, $returnByKey, $typeSearch);
}


function transformDate($fecha)
{
    $dateTime = DateTime::createFromFormat('d/m/Y', $fecha);
    if ($dateTime) {
        return $dateTime->format('Y-m-d');
    }
    $dateTime = DateTime::createFromFormat('Y/m/d', $fecha);
    if ($dateTime) {
        return $dateTime->format('Y-m-d');
    }
    $dateTime = DateTime::createFromFormat('d-m-Y', $fecha);
    if ($dateTime) {
        return $dateTime->format('Y-m-d');
    }

    return $fecha;
}

function transformCodZonaTerritorialResidencia($value)
{
    $newValue = $value;

    if (Str::upper($value) == 'R') {
        $newValue = '01';
    }
    if (Str::upper($value) == 'U') {
        $newValue = '02';
    }

    return $newValue;
}

function convertNullToEmptyString(array $data): array
{
    return array_map(function ($item) {
        return $item === null ? '' : $item;
    }, $data);
}


function parseDate($fecha)
{
    // Separar la fecha en día, mes y año
    $dateType = explode('-', $fecha); // Si no funciona con '/', probar con '-'
    if (!isset($dateType[0]) || !isset($dateType[1]) || !isset($dateType[2])) {
        [$dia, $mes, $ano] = explode('/', $fecha);
    }

    // Crear un objeto DateTime con la fecha en el formato original
    $datetime = DateTime::createFromFormat('d/m/Y', $fecha);
    if (!$datetime) {
        $datetime = DateTime::createFromFormat('d-m-Y', $fecha); // Si no funciona con '/', probar con '-'

        if (!$datetime) {
            $datetime = DateTime::createFromFormat('Y-m-d', $fecha); // Si no funciona con '/', probar con '-'

            if (!$datetime) {
                $datetime = DateTime::createFromFormat('Y/m/d', $fecha); // Si no funciona con '/', probar con '-'
            }
        }
    }

    // Formatear la fecha como 'Y-m-d'
    // $fecha = $datetime->format('Y-m-d');

    $fecha = Carbon::parse($datetime);

    // Validar el valor devuelto
    if (!$fecha) {
        throw new Exception('Formato de fecha invalido1');
    }

    // Validar el valor devuelto por DateTime::createFromFormat()
    if (!$datetime) {
        throw new Exception('Formato de fecha invalido2');
    }

    return $fecha;
}
