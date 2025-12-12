<?php

namespace App\QueryBuilder\Sort;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Sorts\Sort;

class IsActiveSort implements Sort
{
    public function __invoke($query, $descending, $property): Builder
    {
        if ($descending) {
            // When descending, active (1) records come first
            return $query->orderBy('is_active', 'desc');
        } else {
            // When ascending, inactive (0) records come first
            return $query->orderBy('is_active', 'asc');
        }
    }
}
