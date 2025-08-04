<?php

namespace App\QueryBuilder\Filters;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

class DataSelectFilter implements Filter
{
    protected $relation;

    public function __construct($relation = null)
    {
        $this->relation = $relation;
    }

    /**
     * Apply the filter to the query.
     *
     * @param  mixed  $value
     * @return void
     */
    public function __invoke(Builder $query, $value, string $property)
    {

        $values = is_array($value) ? $value : explode(',', $value);

        // Extraemos solo la parte numérica de cada elemento
        $arrayIds = array_map(function ($val) {
            return explode('|', $val)[0]; // Ej: "239|venezuela" → "239"
        }, $values);

        if (! empty($this->relation)) {
            $query->whereHas($this->relation, function ($q) use ($property, $arrayIds) {
                $q->whereIn($property, $arrayIds);
            });
        } else {
            $query->whereIn($property, $arrayIds);
        }
    }
}
