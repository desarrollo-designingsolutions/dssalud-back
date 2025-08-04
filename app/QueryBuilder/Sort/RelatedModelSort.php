<?php

namespace App\QueryBuilder\Sort;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Sorts\Sort;

class RelatedModelSort implements Sort
{
    protected $relationship;

    protected $relatedTable;

    protected $relatedColumn;

    protected $morphType;

    protected $morphClass;

    protected $uniqueId;

    public function __construct(string $relationship, string $relatedTable, string $relatedColumn, ?string $morphType = null, ?string $morphClass = null)
    {
        $this->relationship = $relationship;
        $this->relatedTable = $relatedTable;
        $this->relatedColumn = $relatedColumn;
        $this->morphType = $morphType;
        $this->morphClass = $morphClass;
        $this->uniqueId = uniqid(); // Generate a unique ID for this sort instance
    }

    public function __invoke(Builder $query, bool $descending, string $property)
    {
        $direction = $descending ? 'DESC' : 'ASC';

        // Split the relationship path (e.g., 'scheduleable.third')
        $relationships = explode('.', $this->relationship);

        $currentTable = $query->getModel()->getTable();
        $currentQuery = $query;

        // Use unique aliases for each join
        $aliasCounter = 0;
        $baseAlias = $this->uniqueId; // Use uniqueId to differentiate aliases for this sort instance

        foreach ($relationships as $index => $relation) {
            if ($index === 0 && $this->morphType && $this->morphClass) {
                // Handle morph relationship (e.g., schedules -> schedule_conciliations)
                $morphAlias = "sc_alias_{$baseAlias}_{$aliasCounter}";
                $currentQuery->join("schedule_conciliations as {$morphAlias}", function ($join) use ($currentTable, $relation, $morphAlias) {
                    $join->on("{$currentTable}.{$relation}_id", '=', "{$morphAlias}.id")
                        ->where("{$currentTable}.{$this->morphType}", '=', $this->morphClass);
                });
                $currentTable = $morphAlias;
                $aliasCounter++;
            }

            if ($index === count($relationships) - 1) {
                // Last relationship (e.g., schedule_conciliations -> thirds or users)
                $finalAlias = "{$this->relatedTable}_{$baseAlias}_{$aliasCounter}";
                $currentQuery->join("{$this->relatedTable} as {$finalAlias}", "{$currentTable}.{$relation}_id", '=', "{$finalAlias}.id");
                $currentTable = $finalAlias;
            }
        }

        // Apply the sort on the final table's column
        $currentQuery->orderBy("{$currentTable}.{$this->relatedColumn}", $direction);
    }
}
