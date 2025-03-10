<?php

namespace App\QueryBuilder\Sort;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Sorts\Sort;

class UserFullNameSort implements Sort
{
    public function __invoke(Builder $query, bool $descending, string $property): Builder
    {
        // Usamos `orderByRaw` para ordenar por la concatenación de `name` y `surname`
        $direction = $descending ? 'desc' : 'asc';

        return $query->orderByRaw("CONCAT(users.name, ' ', users.surname) $direction");
    }
}
