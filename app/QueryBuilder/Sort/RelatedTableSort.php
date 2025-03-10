<?php

namespace App\QueryBuilder\Sort;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Sorts\Sort;

class RelatedTableSort implements Sort
{
    private string $primaryTable;      // Tabla principal (ej: grades)

    private string $relatedTable;      // Tabla relacionada (ej: type_education)

    private string $sortField;         // Campo por el que se ordena (ej: name)

    private string $foreignKey;        // Llave foránea en la tabla principal (ej: type_education_id)

    public function __construct(string $primaryTable, string $relatedTable, string $sortField, string $foreignKey)
    {
        $this->primaryTable = $primaryTable;
        $this->relatedTable = $relatedTable;
        $this->sortField = $sortField;
        $this->foreignKey = $foreignKey;
    }

    public function __invoke(Builder $query, bool $descending, string $property): Builder
    {
        $direction = $descending ? 'desc' : 'asc';

        return $query->join($this->relatedTable, "{$this->primaryTable}.{$this->foreignKey}", '=', "{$this->relatedTable}.id")
            ->orderBy("{$this->relatedTable}.{$this->sortField}", $direction);
    }
}
