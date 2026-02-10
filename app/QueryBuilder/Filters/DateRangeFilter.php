<?php

namespace App\QueryBuilder\Filters;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

class DateRangeFilter implements Filter
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

        if (is_string($value) && strpos($value, ' to ') !== false) {
            [$startDate, $endDate] = explode(' to ', $value);
            try {
                $start = Carbon::parse($startDate)->startOfDay();
                $end = Carbon::parse($endDate)->endOfDay();

                if (! empty($this->relation)) {
                    $query->whereHas($this->relation, function ($q) use ($property, $start, $end) {
                        $q->whereBetween($property, [$start, $end]);
                    });
                } else {
                    $query->whereBetween($property, [$start, $end]);
                }
            } catch (\Exception $e) {
                return; // Ignorar si las fechas son inválidas
            }
        } else {
            try {
                $date = Carbon::parse($value);

                if (! empty($this->relation)) {
                    $query->whereHas($this->relation, function ($q) use ($property, $date) {
                        $q->whereDate($property, '=', $date);
                    });
                } else {
                    $query->whereDate($property, '=', $date);
                }
            } catch (\Exception $e) {
                return;
            }
        }
    }
}
