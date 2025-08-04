<?php

namespace App\QueryBuilder\Sort;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Sorts\Sort;

class RelatedTableSort implements Sort
{
    private string $primaryTable;      // Tabla principal (ej: inspections)

    private string $relatedTable;      // Tabla relacionada (ej: vehicles)

    private string $sortField;         // Campo por el que se ordena (ej: license_plate)

    private string $foreignKey;        // Llave foránea en la tabla principal (ej: vehicle_id)

    private string $alias;             // Alias único para la tabla relacionada

    public function __construct(string $primaryTable, string $relatedTable, string $sortField, string $foreignKey)
    {
        $this->primaryTable = $primaryTable;
        $this->relatedTable = $relatedTable;
        $this->sortField = $sortField;
        $this->foreignKey = $foreignKey;
        // Generar un alias único basado en la tabla y el campo de ordenación
        $this->alias = "{$relatedTable}_for_{$sortField}";
    }

    public function __invoke(Builder $query, bool $descending, string $property): Builder
    {
        $direction = $descending ? 'desc' : 'asc';

        // Usar el alias en el join
        return $query->join("{$this->relatedTable} as {$this->alias}", "{$this->primaryTable}.{$this->foreignKey}", '=', "{$this->alias}.id")
            ->orderBy("{$this->alias}.{$this->sortField}", $direction);
    }
}
