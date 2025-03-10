<?php

namespace App\QueryBuilder\Filters;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

class IsActiveFilter implements Filter
{
    public function __invoke(Builder $query, $value, string $property)
    {
        // Normalizamos el valor recibido a un booleano (true/false) o null si no es válido
        $isActive = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        // Si el valor es válido (no null), aplicamos el filtro
        if ($isActive !== null) {
            $query->where('is_active', $isActive ? 1 : 0);
        }
    }
}
