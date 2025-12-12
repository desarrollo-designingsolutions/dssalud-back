<?php

namespace App\Repositories;

use App\Enums\Role\RoleTypeEnum;
use App\Helpers\Constants;
use App\Models\User;
use App\QueryBuilder\Filters\QueryFilters;
use App\QueryBuilder\Sort\IsActiveSort;
use App\QueryBuilder\Sort\RelatedTableSort;
use App\QueryBuilder\Sort\UserFullNameSort;
use App\Traits\AuditMap;
use Illuminate\Support\Facades\Auth;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class UserRepository extends BaseRepository
{
    use AuditMap;

    public function __construct(User $modelo)
    {
        parent::__construct($modelo);
    }

    public function paginate($request = [])
    {
        $cacheKey = $this->cacheService->generateKey("{$this->model->getTable()}_paginate", $request, 'string');

        return $this->cacheService->remember($cacheKey, function () use ($request) {
            $query = QueryBuilder::for($this->model->query())
                ->with(['role:id,description'])
                ->select(['users.id', 'users.name', 'users.surname', 'users.email', 'users.is_active', 'users.role_id'])
                ->allowedFilters([
                    'is_active',
                    'email',
                    'name',
                    AllowedFilter::callback('inputGeneral', function ($query, $value) {
                        $query->where(function ($query) use ($value) {
                            $query->orWhereRaw("CONCAT(users.name, ' ', users.surname) LIKE ?", ["%{$value}%"]);

                            $query->orWhereHas('role', function ($query) use ($value) {
                                $query->where('description', 'like', "%$value%");
                            });

                            $query->orWhere('users.email', 'like', "%$value%");
                            QueryFilters::filterByText($query, $value, 'is_active', [
                                'activo' => 1,
                                'inactivo' => 0,
                            ]);
                        });
                    }),
                ])
                ->allowedSorts([
                    AllowedSort::custom('full_name', new UserFullNameSort),
                    'email',
                    AllowedSort::custom('role_description', new RelatedTableSort('users', 'roles', 'description', 'role_id')),
                    AllowedSort::custom('is_active', new IsActiveSort),
                ])->where(function ($query) use ($request) {
                    if (! empty($request['company_id'])) {
                        $query->where('users.company_id', $request['company_id']);
                    }
                })
                ->paginate(request()->perPage ?? Constants::ITEMS_PER_PAGE);

            return $query;
        }, Constants::REDIS_TTL);
    }

    public function list($request = [], $with = [], $select = ['*'], $idsAllowed = [], $idsNotAllowed = [])
    {
        $data = $this->model->with($with)->where(function ($query) {})
            ->where(function ($query) use ($request) {

                if (! empty($request['is_active'])) {
                    $query->where('is_active', $request['is_active']);
                }
                if (! empty($request['company_id'])) {
                    $query->where('company_id', $request['company_id']);
                }
            })
            ->where(function ($query) use ($request) {
                if (isset($request['searchQueryInfinite']) && ! empty($request['searchQueryInfinite'])) {
                    $query->orWhere('name', 'like', '%'.$request['searchQueryInfinite'].'%');
                    $query->orWhere('surname', 'like', '%'.$request['searchQueryInfinite'].'%');
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

    public function store($request, $id = null, $withCompany = true)
    {
        $validatedData = $this->clearNull($request);

        $idToUse = $id ?? ($validatedData['id'] ?? null);

        if ($idToUse) {
            $data = $this->model->find($idToUse);
        } else {
            $data = $this->model::newModelInstance();
            if ($withCompany) {
                $data->company_id = auth()->user()->company_id;
            }
        }

        foreach ($request as $key => $value) {
            $data[$key] = is_array($request[$key]) ? $request[$key]['value'] : $request[$key];
        }

        if (! empty($validatedData['password'])) {
            $data->password = $validatedData['password'];
        } else {
            unset($data->password);
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

    public function getAuditUsers($request = [])
    {
        $data = $this->model->where(function ($query) use ($request) {
            if (! empty($request['is_active'])) {
                $query->where('is_active', $request['is_active']);
            }
            if (! empty($request['company_id'])) {
                $query->where('company_id', $request['company_id']);
            }
        })
            ->whereHas('roles', function ($subQuery) {
                $subQuery->where('type', RoleTypeEnum::ROLE_TYPE_001);
            });

        $data = $data->orderBy('id', 'desc');
        if (empty($request['typeData'])) {
            $data = $data->paginate($request['perPage'] ?? 10);
        } else {
            $data = $data->get();
        }

        return $data;
    }
}
