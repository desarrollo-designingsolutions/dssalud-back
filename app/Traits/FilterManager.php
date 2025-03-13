<?php

namespace App\Traits;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Request;

/**
 * Trait FilterManager
 *
 * Proporciona métodos para gestionar filtros en solicitudes, permitiendo eliminar filtros no válidos
 * y devolver respuestas vacías cuando se usan claves inaplicables.
 */
trait FilterManager
{
    /**
     * Elimina los filtros especificados de la solicitud actual.
     *
     * Este método toma un array de filtros no válidos y los elimina de los filtros presentes en la
     * solicitud HTTP global. Si no se especifican filtros no válidos, no realiza ninguna acción.
     *
     * @param  array  $invalidFilters  Filtros no válidos a eliminar. Por ejemplo: ['nit', 'otro'].
     *                                 Si está vacío, el método termina sin modificar la solicitud.
     * @return void
     *
     * @example
     * // Solicitud: ?filter[nit]=12345&filter[is_active]=1
     * $this->removeInvalidFilters(['nit']);
     * // Resultado: Request::input('filter') = ['is_active' => '1']
     */
    protected function removeInvalidFilters(array $invalidFilters = [])
    {
        if (empty($invalidFilters)) {
            return;
        }

        $filters = Request::input('filter', []);

        foreach ($invalidFilters as $filter) {
            if (isset($filters[$filter])) {
                unset($filters[$filter]);
            }
        }

        Request::merge(['filter' => $filters]);
    }

    /**
     * Verifica si hay claves inválidas en los filtros y devuelve una paginación vacía si es así.
     *
     * Este método revisa si alguna de las claves especificadas como inválidas está presente en los
     * filtros de la solicitud (ya sea un array pasado o la solicitud HTTP global). Si encuentra
     * alguna, devuelve una paginación vacía. Si no, permite que el flujo continúe.
     *
     * @param  array  $request  Solicitud como array (opcional). Si no se proporciona, se usa la solicitud HTTP.
     * @param  array  $invalidKeys  Claves que, si están presentes, deben resultar en una respuesta vacía.
     *                              Por ejemplo: ['nit']. Si está vacío, no realiza ninguna acción.
     * @return LengthAwarePaginator|null Retorna una paginación vacía si hay claves inválidas, null si no.
     *
     * @example
     * // Solicitud: ?filter[nit]=12345&filter[is_active]=1
     * $result = $this->handleInvalidFilters([], ['nit']);
     * // Resultado: $result es una paginación vacía (data: [], total: 0)
     *
     * // Solicitud: ?filter[is_active]=1
     * $result = $this->handleInvalidFilters([], ['nit']);
     * // Resultado: $result es null, el flujo continúa
     */
    protected function handleInvalidFilters(array $invalidKeys = [])
    {
        if (empty($invalidKeys)) {
            return null;
        }

        $filters = Request::input('filter', []);

        foreach ($invalidKeys as $key) {
            if (isset($filters[$key])) {
                return new LengthAwarePaginator(
                    [], // Items vacíos
                    0,  // Total
                    request()->perPage,
                    1,  // Página actual
                    ['path' => Request::url()] // URL para paginación
                );
            }
        }

        return null;
    }
}
