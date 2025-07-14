<?php

namespace App\QueryBuilder\Filters;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

class DataSelectRelationFilter implements Filter
{
    private string $relation;

    private string $field;

    public function __construct(string $relation = '', string $field = '')
    {
        $this->relation = $relation;
        $this->field = $field;
    }

    public function __invoke(Builder $query, $value, string $property)
    {
        $relation = ! empty($this->relation) ? $this->relation : $property;
        $field = ! empty($this->field) ? $this->field : 'id';

        // Uso AllowedFilter::callback('key', new DataSelectFilter()),
        $values = is_array($value) ? $value : explode(',', $value);

        // Extraemos solo la parte numérica de cada elemento
        $arrayIds = array_map(function ($val) {
            return explode('|', $val)[0]; // Ej: "239|venezuela" → "239"
        }, $values);

        $query->whereHas($relation, function ($subQuery) use ($field, $arrayIds) {
            $subQuery->whereIn($field, $arrayIds);
        });
    }
}
