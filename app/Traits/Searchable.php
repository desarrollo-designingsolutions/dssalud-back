<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

trait Searchable
{
    /**
     * Aplicar una búsqueda global en todos los campos del modelo y sus relaciones.
     */
    public function scopeSearch(Builder $query, string $term, array $relations = []): Builder
    {
        // Obtén todos los campos de la tabla correspondiente al modelo
        $columns = Schema::getColumnListing($this->getTable());

        if (array_key_exists('all', $relations)) {
            $columns = $relations['all'];

            // Verifica que las columnas existen en la tabla
            $validColumns = array_filter($columns, function ($column) {
                return Schema::hasColumn($this->getTable(), $column);
            });

            // Agregar condiciones `orWhere` para cada campo del modelo principal
            $query->where(function ($query) use ($validColumns, $term) {
                foreach ($validColumns as $column) {
                    $query->orWhere($column, 'LIKE', "%{$term}%");
                }
            });
            unset($relations['all']);
        }

        // Si se especifican relaciones para buscar, aplicar condiciones
        if (! empty($relations)) {
            foreach ($relations as $relation => $relationColumns) {
                $findRelation = $relation; // relaciona  buscar
                if ((strpos($relation, '.') !== false)) { // si la relacion o palabra tiene "."
                    $findRelation = explode('.', $relation);
                    $findRelation = $findRelation[0]; // debo obtener el primer valor y solo este se busca en la class o modelo
                }

                if ((method_exists($this, $findRelation))) { // busco la relacion en mi modelo
                    $this->queryPerzonalized($query, $relation, $relationColumns, $term);
                }
            }
        }

        return $query;
    }

    protected function queryPerzonalized(&$query, $relation, $relationColumns, $term): void
    {
        $query->orWhereHas($relation, function ($query) use ($relationColumns, $term) {
            $query->where(function ($query) use ($relationColumns, $term) {
                foreach ($relationColumns as $column) {
                    $query->orWhere(strval($column), 'LIKE', "%{$term}%");
                }
            });
        });
    }

    /**
     * Obtener los atributos registrados en el modelo.
     */
    protected function getModelAttributes(): array
    {
        $model = $this->getModel();
        $reflection = new \ReflectionClass($model);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        $attributes = [];
        foreach ($methods as $method) {
            if (Str::startsWith($method->name, 'get') && Str::endsWith($method->name, 'Attribute')) {
                $attribute = Str::snake(Str::replaceLast('Attribute', '', Str::after($method->name, 'get')));
                $attributes[] = $attribute;
            }
        }

        return $attributes;
    }
}
