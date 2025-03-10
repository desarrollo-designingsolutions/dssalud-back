<?php

namespace App\QueryBuilder\Filters;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

class DateRangeFilter implements Filter
{
    public function __invoke(Builder $query, $value, string $property)
    {
        if (is_string($value) && strpos($value, ' to ') !== false) {
            [$startDate, $endDate] = explode(' to ', $value);
            try {
                $start = Carbon::parse($startDate)->startOfDay();
                $end = Carbon::parse($endDate)->endOfDay();
                $query->whereBetween($property, [$start, $end]);
            } catch (\Exception $e) {
                return; // Ignorar si las fechas son inválidas
            }
        } else {
            try {
                $date = Carbon::parse($value);
                $query->whereDate($property, '=', $date);
            } catch (\Exception $e) {
                return;
            }
        }
    }
}
