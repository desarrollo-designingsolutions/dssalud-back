<?php

namespace App\QueryBuilder\Sort;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Sorts\Sort;

class StatusOldSort implements Sort
{
    protected $statusOrder;

    /**
     * Constructor que permite definir un arreglo personalizado de estados.
     *
     * @param  array|null  $customStatusOrder  Arreglo de estados con estructura [['value' => ..., 'title' => ...], ...].
     *                                         Si es null, usa el arreglo por defecto.
     */
    public function __construct($customStatusOrder = null)
    {
        $this->statusOrder = $customStatusOrder;
    }

    /**
     * Ordena la consulta por el campo status basado en los títulos traducidos.
     *
     * @param  Builder  $query  El objeto de consulta.
     * @param  bool  $descending  Indica si el orden es descendente (true) o ascendente (false).
     * @param  string  $property  El nombre de la propiedad a ordenar (en este caso, "status").
     */
    public function __invoke($query, $descending, $property): Builder
    {
        // Extraemos solo los valores en el orden deseado (basado en los títulos)
        $orderedValues = array_column($this->statusOrder, 'value');

        // Determinamos la dirección del ordenamiento
        $direction = $descending ? 'desc' : 'asc';

        // Si es descendente, invertimos el arreglo
        if ($descending) {
            $orderedValues = array_reverse($orderedValues);
        }

        // Usamos FIELD para ordenar según el orden personalizado
        return $query->orderByRaw("FIELD($property, '".implode("','", $orderedValues)."') $direction");
    }
}
