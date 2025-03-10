<?php

namespace App\QueryBuilder\Sort;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Sorts\Sort;

class IsActiveSort implements Sort
{
    public function __invoke($query, $descending, $property): Builder
    {
        $direction = $descending ? 'asc' : 'desc'; // Invertimos la dirección

        return $query->orderBy('is_active', $direction);
    }
}
