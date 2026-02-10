<?php

namespace App\QueryBuilder\Sort;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Sorts\Sort;

class DynamicJoinConcatSort implements Sort
{
    private string $concat;

    private string $relatedTable;

    private string $foreignKey;

    private string $primaryKey;

    private string $alias;

    /**
     * Constructor para configurar el sort con JOIN dinámico.
     *
     * @param  string  $concat  Expresión de concatenación (ej. "users.name, ' ', users.surname").
     * @param  string  $relatedTable  Tabla a la que se hace el JOIN (ej. "users").
     * @param  string  $foreignKey  Campo foráneo en la tabla principal (ej. "user_id").
     * @param  string  $primaryKey  Campo primario en la tabla relacionada (ej. "id").
     */
    public function __construct(string $concat, string $relatedTable, string $foreignKey, string $primaryKey = 'id')
    {
        $this->concat = $concat;
        $this->relatedTable = $relatedTable;
        $this->foreignKey = $foreignKey;
        $this->primaryKey = $primaryKey;
        $this->alias = $this->generateAlias();
    }

    /**
     * Genera un alias único para la tabla relacionada.
     */
    private function generateAlias(): string
    {
        // Creamos un alias basado en la tabla y un timestamp para evitar colisiones
        return $this->relatedTable.'_'.$this->foreignKey.'_sort_'.time();
    }

    /**
     * Aplica el ordenamiento con JOIN dinámico y concatenación.
     *
     * @param  Builder  $query  El objeto de consulta.
     * @param  bool  $descending  Indica si el orden es descendente (true) o ascendente (false).
     * @param  string  $property  El nombre de la propiedad a ordenar.
     */
    public function __invoke(Builder $query, bool $descending, string $property): Builder
    {
        $direction = $descending ? 'desc' : 'asc';

        // Reemplazamos el nombre de la tabla en la concatenación con el alias
        $concatWithAlias = str_replace($this->relatedTable.'.', $this->alias.'.', $this->concat);

        // Añadimos el JOIN con el alias único
        $query->leftJoin(
            "{$this->relatedTable} as {$this->alias}",
            "{$this->alias}.{$this->primaryKey}",
            '=',
            $query->getModel()->getTable().'.'.$this->foreignKey
        );

        // Ordenamos usando la concatenación con el alias
        return $query->orderByRaw("CONCAT($concatWithAlias) $direction");
    }
}
