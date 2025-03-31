<?php

namespace App\Repositories;

use App\Enums\Assignment\StatusAssignmentEnum;
use App\Helpers\Constants;
use App\Models\Assignment;
use App\Models\InvoiceAudit;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Third;
use App\QueryBuilder\Sort\DynamicConcatSort;
use App\Traits\AuditMap;
use Illuminate\Support\Facades\Auth;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class AssignmentRepository extends BaseRepository
{
    use AuditMap;

    public function __construct(Assignment $modelo)
    {
        parent::__construct($modelo);
    }

    public function paginateThirds($request = [])
    {
        $cacheKey = $this->cacheService->generateKey("{$this->model->getTable()}_paginateThirds", $request, 'string');

        return $this->cacheService->remember($cacheKey, function () use ($request) {
            $query = QueryBuilder::for(Third::query())
                ->withCount([
                    'invoiceAudits as count_invoice_total' => function ($query) use ($request) {
                        $query->whereHas('assignment', function ($subQuery) use ($request) {
                            $subQuery->where('assignment_batch_id', $request['assignment_batch_id']);
                        });
                    },
                    'invoiceAudits as count_invoice_pending' => function ($query) use ($request) {
                        $query->whereHas('assignment', function ($subQuery) use ($request) {
                            $subQuery->where('assignment_batch_id', $request['assignment_batch_id']);
                            $subQuery->where(function ($subQuery2) {
                                $subQuery2->where('status', StatusAssignmentEnum::ASSIGNMENT_EST_001);
                                $subQuery2->orWhere('status', StatusAssignmentEnum::ASSIGNMENT_EST_002);
                            });
                        });
                    },
                    'invoiceAudits as count_invoice_finish' => function ($query) use ($request) {
                        $query->whereHas('assignment', function ($subQuery) use ($request) {
                            $subQuery->where('assignment_batch_id', $request['assignment_batch_id']);
                            $subQuery->where('status', StatusAssignmentEnum::ASSIGNMENT_EST_003);
                        });
                    },
                ])
                ->addSelect([
                    'total_value_sum' => InvoiceAudit::selectRaw('SUM(total_value)')
                        ->whereColumn('third_id', 'thirds.id')
                        ->whereHas('assignment', function ($subQuery) use ($request) {
                            $subQuery->where('assignment_batch_id', $request['assignment_batch_id']);
                        }),
                ])
                ->allowedFilters([
                    AllowedFilter::callback('inputGeneral', function ($query, $value) {
                        $query->where(function ($subQuery) use ($value) {
                            $subQuery->orWhere('nit', 'like', "%$value%");
                            $subQuery->orWhere('name', 'like', "%$value%");
                        });
                    }),

                ])
                ->allowedSorts([
                    'nit',
                    'name',
                    'count_invoice_total',
                    'count_invoice_pending',
                    'count_invoice_finish',
                    'total_value_sum',
                ])->where(function ($query) use ($request) {

                    $query->whereHas('invoiceAudits.assignment', function ($subQuery) use ($request) {
                        $subQuery->where('assignment_batch_id', $request['assignment_batch_id']);
                    });

                    if (!empty($request['company_id'])) {
                        $query->where('company_id', $request['company_id']);
                    }
                })
                ->paginate(request()->perPage ?? Constants::ITEMS_PER_PAGE);

            return $query;
        }, Constants::REDIS_TTL);
    }

    public function paginateInvoiceAudit($request = [])
    {

        $cacheKey = $this->cacheService->generateKey("{$this->model->getTable()}_paginateInvoiceAudit", $request, 'string');

        return $this->cacheService->remember($cacheKey, function () use ($request) {
            $query = QueryBuilder::for(InvoiceAudit::query())
                ->withCount(['patients', 'services'])
                ->addSelect([
                    'total_value_services' => Service::selectRaw('CAST(SUM(total_value) AS DECIMAL(15,2))')
                        ->whereColumn('invoice_audit_id', 'invoice_audits.id'), // Vincula los services al InvoiceAudit actual
                ])
                ->allowedFilters([
                    AllowedFilter::callback('inputGeneral', function ($query, $value) {
                        $query->orWhere('invoice_number', 'like', "%$value%");
                    }),
                ])

                ->allowedSorts([
                    'invoice_number',
                ])->where(function ($query) use ($request) {

                    if (!empty($request['company_id'])) {
                        $query->whereHas('third.company', function ($subQuery) use ($request) {
                            $subQuery->where('company_id', $request['company_id']);
                        });
                    }

                    if (!empty($request['assignment_batch_id'])) {
                        $query->whereHas('assignment', function ($subQuery) use ($request) {
                            $subQuery->where('assignment_batch_id', $request['assignment_batch_id']);
                        });
                    }

                    if (!empty($request['third_id'])) {
                        $query->where('third_id', $request['third_id']);
                    }

                    if (!empty($request['user_id'])) {
                        $query->where('user_id', $request['user_id']);
                    }
                })
                ->paginate(request()->perPage ?? Constants::ITEMS_PER_PAGE);

            return $query;
        }, Constants::REDIS_TTL);
    }

    public function paginatePatient($request = [])
    {

        $cacheKey = $this->cacheService->generateKey("{$this->model->getTable()}_paginatePatient", $request, 'string');

        return $this->cacheService->remember($cacheKey, function () use ($request) {
            $query = QueryBuilder::for(Patient::query())

                ->allowedFilters([
                    AllowedFilter::callback('inputGeneral', function ($query, $value) {
                        $query->orWhereRaw("CONCAT(patients.first_name, ' ', patients.second_name, ' ', patients.first_surname, ' ', patients.second_surname) LIKE ?", ["%{$value}%"]);

                        $query->orWhere('identification_number', 'like', "%$value%");
                        $query->orWhere('gender', 'like', "%$value%");
                    }),

                ])
                ->allowedSorts([
                    AllowedSort::custom('full_name', new DynamicConcatSort("first_name, ' ', second_name, ' ', first_surname, ' ', second_surname")),
                    'identification_number',
                    'gender',
                ])->where(function ($query) use ($request) {

                    if (!empty($request['invoice_audit_id'])) {
                        $query->where('invoice_audit_id', $request['invoice_audit_id']);
                    }
                })
                ->paginate(request()->perPage ?? Constants::ITEMS_PER_PAGE);

            return $query;
        }, Constants::REDIS_TTL);
    }

    public function store($request)
    {
        $request = $this->clearNull($request);

        if (!empty($request['id'])) {
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
            if (!empty($request['idsAllowed'])) {
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
            if (!empty($request['status'])) {
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

        if (!$data) {
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

    public function countNumberProviders($request = [])
    {
        $data = $this->model::query()
            ->where(function ($query) use ($request) {
                $query->whereHas('assignmentBatche', function ($subQuery) use ($request) {
                    if (!empty($request['company_id'])) {
                        $subQuery->where('company_id', $request['company_id']);
                    }
                });
                $query->whereHas('invoiceAudit', function ($subQuery) use ($request) {
                    if (!empty($request['third_id'])) {
                        $subQuery->where('third_id', $request['third_id']);
                    }
                });

                if (!empty($request['user_id'])) {
                    $query->where('user_id', $request['user_id']);
                }

                if (!empty($request['status_iqual_to'])) {
                    $query->whereIn('status', $request['status_iqual_to']);
                }

                if (!empty($request['assignment_batch_id'])) {
                    $query->where('assignment_batch_id', $request['assignment_batch_id']);
                }
            })->count();

        return $data;
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
