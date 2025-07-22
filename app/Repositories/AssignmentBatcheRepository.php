<?php

namespace App\Repositories;

use App\Enums\Assignment\StatusAssignmentEnum;
use App\Helpers\Constants;
use App\Models\AssignmentBatche;
use App\Traits\AuditMap;
use Illuminate\Support\Facades\Auth;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AssignmentBatcheRepository extends BaseRepository
{
    use AuditMap;

    public function __construct(AssignmentBatche $modelo)
    {
        parent::__construct($modelo);
    }

    public function paginate($request = [])
    {
        $cacheKey = $this->cacheService->generateKey("{$this->model->getTable()}_paginate", $request, 'string');

        $data = request();

        return $this->cacheService->remember($cacheKey, function () use ($request) {
            $query = QueryBuilder::for($this->model->query())
                ->withCount([
                    'assignments as count_invoice',
                    'assignments as count_invoice_pending' => function ($query) {
                        $query->whereNotIn('status', [StatusAssignmentEnum::ASSIGNMENT_EST_003]);
                    },
                    'assignments as count_invoice_completed' => function ($query) {
                        $query->where('status', StatusAssignmentEnum::ASSIGNMENT_EST_003);
                    },
                ])
                ->allowedFilters([
                    AllowedFilter::callback('inputGeneral', function ($query, $value) {
                        $query->orWhere('description', 'like', "%$value%");
                    }),
                ])
                ->allowedSorts([
                    'description',
                    'count_invoice',
                    'count_invoice_pending',
                    'count_invoice_completed',
                ])
                ->where(function ($query) use ($request) {
                    if (! empty($request['company_id'])) {
                        $query->where('company_id', $request['company_id']);
                    }
                });

            // if (isset($data['filter']['inputGeneral']) && is_numeric($data['filter']['inputGeneral'])) {
            //     $value = $data['filter']['inputGeneral'];
            //     $query->havingRaw("CAST(count_invoice AS CHAR) LIKE ?", ["%$value%"]);
            // }

            $query = $query->paginate(request()->perPage ?? Constants::ITEMS_PER_PAGE);

            return $query;
        }, Constants::REDIS_TTL);
    }

    public function list($request = [], $with = [], $select = ['*'], $idsAllowed = [], $idsNotAllowed = [])
    {
        $data = $this->model->with($with)->where(function ($query) {})
            ->where(function ($query) use ($request) {

                if (! empty($request['company_id'])) {
                    $query->where('company_id', $request['company_id']);
                }
            })
            ->where(function ($query) use ($request) {
                if (isset($request['searchQueryInfinite']) && ! empty($request['searchQueryInfinite'])) {
                    $query->orWhere('description', 'like', '%'.$request['searchQueryInfinite'].'%');
                }
            });

        $data = $data->orderBy('id', 'desc');
        if (empty($request['typeData'])) {
            $data = $data->paginate($request['perPage'] ?? 10);
        } else {
            $data = $data->get();
        }

        return $data;
    }

    public function store($request)
    {
        $request = $this->clearNull($request);

        if (! empty($request['id'])) {
            $data = $this->model->find($request['id']);
        } else {
            $data = $this->model::newModelInstance();
        }

        foreach ($request as $key => $value) {
            $data[$key] = is_array($request[$key]) ? $request[$key]['value'] : $request[$key];
        }

        $data->save();

        return $data;
    }

    public function register($request)
    {
        $data = $this->model;

        foreach ($request as $key => $value) {
            $data[$key] = $request[$key];
        }

        $data->save();

        return $data;
    }

    public function findByEmail($email)
    {
        return $this->model::where('email', $email)->first();
    }

    public function selectList($request = [], $with = [], $select = [], $fieldValue = 'id', $fieldTitle = 'name')
    {
        $data = $this->model->with($with)->where(function ($query) use ($request) {
            if (! empty($request['idsAllowed'])) {
                $query->whereIn('id', $request['idsAllowed']);
            }

            $query->where('is_active', true);
            $query->where('company_id', auth()->user()->company_id);
        })->get()->map(function ($value) use ($with, $select, $fieldValue, $fieldTitle) {
            $data = [
                'value' => $value->$fieldValue,
                'title' => $value->$fieldTitle,
            ];

            if (count($select) > 0) {
                foreach ($select as $s) {
                    $data[$s] = $value->$s;
                }
            }
            if (count($with) > 0) {
                foreach ($with as $s) {
                    $data[$s] = $value->$s;
                }
            }

            return $data;
        });

        return $data;
    }

    public function countData($request = [])
    {
        $data = $this->model->where(function ($query) use ($request) {
            if (! empty($request['status'])) {
                $query->where('status', $request['status']);
            }

            // rol_in_id
            if (isset($request['rol_in_id']) && count($request['rol_in_id']) > 0) {
                $query->whereIn('role_id', $request['rol_in_id']);
            }
            // divisio_in_id
            if (isset($request['division_in_id']) && count($request['division_in_id']) > 0) {
                $query->whereIn('branch_division_id', $request['division_in_id']);
            }
            $query->where('company_id', Auth::user()->company_id);
            $query->where('role_id', '!=', 1);
        })->count();

        return $data;
    }

    public function timeLine($request = [])
    {
        $typeData = $request['typeData'] ?? 'all';

        // Cargar los datos con relaciones, incluyendo los eliminados
        $data = $this->model::find($request['auditable_id']);

        if (! $data) {
            return collect(); // Si no hay datos, devolver una colección vacía
        }

        // Obtener todos los audits (del modelo principal y de los comentarios)
        $audits = $this->getAllAudits($data);

        // Aplicar el mapeo de columnas dinámicamente según el modelo de cada audit
        $this->applyColumnMappingToAudits($audits);

        // Ordenar por 'created_at' en orden descendente
        $audits = $audits->sortByDesc('created_at');

        // Devolver el resultado según el tipo de datos solicitado
        return $typeData === 'count' ? $audits->count() : $audits;
    }

    /**
     * Obtener todos los audits del modelo principal y de sus comentarios.
     */
    protected function getAllAudits($data)
    {
        $audits = $data->audits;
        $relations = [];

        // Cargar los audits de las relaciones, incluyendo los eliminados (soft deleted)
        foreach ($relations as $relation) {
            foreach ($data->$relation()->withTrashed()->get() as $element) {
                $audits = $audits->merge($element->audits);
            }
        }

        return $audits;
    }
}
