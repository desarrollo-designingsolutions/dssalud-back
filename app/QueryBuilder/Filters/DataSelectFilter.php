<?php

namespace App\QueryBuilder\Filters;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

class DataSelectFilter implements Filter
{
    public function __invoke(Builder $query, $value, string $property)
    {

        $values = is_array($value) ? $value : explode(',', $value);

        // Extraemos solo la parte numérica de cada elemento
        $arrayIds = array_map(function ($val) {
            return explode('|', $val)[0]; // Ej: "239|venezuela" → "239"
        }, $values);

        $query->whereIn($property, $arrayIds);

    }
}
