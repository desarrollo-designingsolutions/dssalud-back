<?php

namespace App\QueryBuilder\Sort;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Sorts\Sort;

class DynamicConcatSort implements Sort
{
    private string $concat;

    public function __construct(string $concat)
    {
        $this->concat = $concat;
    }

    public function __invoke(Builder $query, bool $descending, string $property): Builder
    {
        // Usamos `orderByRaw` para ordenar por la concatenación de `name` y `surname`
        $direction = $descending ? 'desc' : 'asc';

        return $query->orderByRaw("CONCAT($this->concat) $direction");
    }
}
