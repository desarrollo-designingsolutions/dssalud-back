<?php

namespace App\Repositories;

use App\Enums\Assignment\StatusAssignmentEnum;
use App\Helpers\Constants;
use App\Models\AssignmentBatche;
use App\Models\InvoiceAudit;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Third;
use App\QueryBuilder\Sort\DynamicConcatSort;
use Illuminate\Support\Facades\Redis;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class InvoiceAuditRepository extends BaseRepository
{
    public function __construct(InvoiceAudit $modelo)
    {
        parent::__construct($modelo);
    }

    public function list($request = [], $with = [], $select = ['*'], $idsAllowed = [], $idsNotAllowed = [])
    {
        $data = $this->model->with($with)->where(function ($query) {})
            ->where(function ($query) use ($request) {
                filterComponent($query, $request);

                if (! empty($request['company_id'])) {
                    $query->where('company_id', $request['company_id']);
                }
            })
            ->where(function ($query) use ($request) {
                if (isset($request['searchQueryInfinite']) && ! empty($request['searchQueryInfinite'])) {
                    $query->orWhere('name', 'like', '%'.$request['searchQueryInfinite'].'%');
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

    public function paginateBatche($request = [])
    {
        $cacheKey = $this->cacheService->generateKey("{$this->model->getTable()}_paginateBatche", $request, 'string');

        return $this->cacheService->remember($cacheKey, function () use ($request) {
            $query = QueryBuilder::for(AssignmentBatche::query())
                ->withCount([
                    'assignments as count_invoice' => function ($query) use ($request) {
                        $query->where('user_id', $request['user_id']);
                    },
                    'assignments as count_invoice_pending' => function ($query) use ($request) {
                        $query->where('user_id', $request['user_id']);
                        $query->where('status', '=', StatusAssignmentEnum::ASSIGNMENT_EST_002);
                    },
                    'assignments as count_invoice_completed' => function ($query) use ($request) {
                        $query->where('user_id', $request['user_id']);
                        $query->where('status', StatusAssignmentEnum::ASSIGNMENT_EST_003);
                    },
                ]) // Esto agrega la columna "assignments_count"
                ->allowedFilters([
                    AllowedFilter::callback('inputGeneral', function ($query, $value) {
                        $query->orWhere('description', 'like', "%$value%");
                        $query->orWhere('status', 'like', "%$value%");
                    }),
                ])
                ->allowedSorts([
                    'description',
                ])
                ->where(function ($query) use ($request) {
                    $query->whereHas('assignments', function ($subQuery) {
                        $subQuery->where('user_id', request('user_id'));
                    });

                    if (! empty($request['company_id'])) {
                        $query->where('company_id', $request['company_id']);
                    }
                })
                ->paginate(request()->perPage ?? Constants::ITEMS_PER_PAGE);

            return $query;
        }, Constants::REDIS_TTL);
    }

    public function paginateThirds($request = [])
    {
        $cacheKey = $this->cacheService->generateKey("{$this->model->getTable()}_paginateThirds", $request, 'string');

        return $this->cacheService->remember($cacheKey, function () use ($request) {
            $query = QueryBuilder::for(Third::query())
                ->withCount([
                    'invoiceAudits as count_invoice_total' => function ($query) use ($request) {
                        $query->whereHas('assignment', function ($subQuery) use ($request) {
                            $subQuery->where('user_id', $request['user_id']);
                            $subQuery->where('assignment_batch_id', $request['assignment_batch_id']);
                        });
                    },
                    'invoiceAudits as count_invoice_pending' => function ($query) use ($request) {
                        $query->whereHas('assignment', function ($subQuery) use ($request) {
                            $subQuery->where('assignment_batch_id', $request['assignment_batch_id']);
                            $subQuery->where('user_id', $request['user_id']);

                            $subQuery->where(function ($subQuery2) {
                                $subQuery2->where('status', StatusAssignmentEnum::ASSIGNMENT_EST_001);
                                $subQuery2->orWhere('status', StatusAssignmentEnum::ASSIGNMENT_EST_002);
                            });
                        });
                    },
                    'invoiceAudits as count_invoice_finish' => function ($query) use ($request) {
                        $query->whereHas('assignment', function ($subQuery) use ($request) {
                            $subQuery->where('user_id', $request['user_id']);
                            $subQuery->where('assignment_batch_id', $request['assignment_batch_id']);
                            $subQuery->where('status', StatusAssignmentEnum::ASSIGNMENT_EST_003);
                        });
                    },
                ])

                ->allowedFilters([

                    AllowedFilter::callback('inputGeneral', function ($query, $value) {
                        $query->where(function ($subQuery) use ($value) {
                            $subQuery->where('nit', 'like', "%$value%")
                                ->orWhere('name', 'like', "%$value%");
                        });
                    }),

                ])
                ->allowedSorts([
                    'nit',
                    'name',
                ])->where(function ($query) use ($request) {

                    $query->whereHas('invoiceAudits.assignment', function ($subQuery) use ($request) {
                        $subQuery->where('user_id', $request['user_id']);
                        $subQuery->where('assignment_batch_id', $request['assignment_batch_id']);
                    });

                    if (! empty($request['company_id'])) {
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
                ->allowedFilters([

                    AllowedFilter::callback('inputGeneral', function ($query, $value) {

                        $query->orWhere('invoice_number', 'like', "%$value%");
                    }),

                ])
                ->allowedSorts([
                    'invoice_number',
                ])->where(function ($query) use ($request) {
                    if (! empty($request['company_id'])) {
                        $query->whereHas('third.company', function ($subQuery) use ($request) {
                            $subQuery->where('company_id', $request['company_id']);
                        });
                    }

                    if (! empty($request['assignment_batch_id'])) {
                        $query->whereHas('assignment', function ($subQuery) use ($request) {
                            $subQuery->where('assignment_batch_id', $request['assignment_batch_id']);
                        });
                    }

                    if (! empty($request['third_id'])) {
                        $query->where('third_id', $request['third_id']);
                    }
                })
                ->paginate(request()->perPage ?? Constants::ITEMS_PER_PAGE);

            return $query;
        }, Constants::REDIS_TTL);
    }

    public function paginateServices($request = [])
    {

        $cacheKey = $this->cacheService->generateKey("{$this->model->getTable()}_paginateServices", $request, 'string');

        return $this->cacheService->remember($cacheKey, function () use ($request) {
            $query = QueryBuilder::for(Service::query())
                ->allowedFilters([

                    AllowedFilter::callback('inputGeneral', function ($query, $value) {
                        $query->where(function ($subQuery) use ($value) {
                            $subQuery->orWhere('id', 'like', "%$value%");
                            $subQuery->orWhere('detail_code', 'like', "%$value%");
                            $subQuery->orWhere('description', 'like', "%$value%");
                            $subQuery->orWhere('quantity', 'like', "%$value%");
                            $subQuery->orWhere(function ($subQuery) use ($value) {
                                $normalizedValue = preg_replace('/[\$\s\.,]/', '', $value);
                                $subQuery->orWhere('unit_value', 'like', "%$normalizedValue%");
                                $subQuery->orWhere('total_value', 'like', "%$normalizedValue%");
                            });
                        });
                    }),

                ])
                ->allowedSorts([
                    'id',
                    'detail_code',
                    'description',
                    'quantity',
                    'unit_value',
                    'total_value',
                ])->where(function ($query) use ($request) {

                    if (! empty($request['invoice_audit_id'])) {
                        $query->where('invoice_audit_id', $request['invoice_audit_id']);
                    }
                    if (! empty($request['patient_id'])) {
                        $query->where('patient_id', $request['patient_id']);
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

                    if (! empty($request['invoice_audit_id'])) {
                        $query->where('invoice_audit_id', $request['invoice_audit_id']);
                    }
                })
                ->paginate(request()->perPage ?? Constants::ITEMS_PER_PAGE);

            return $query;
        }, Constants::REDIS_TTL);
    }

    public function store(array $request)
    {
        $request = $this->clearNull($request);

        if (! empty($request['id'])) {
            $data = $this->model->find($request['id']);
        } else {
            $data = $this->model::newModelInstance();
        }

        foreach ($request as $key => $value) {
            $data[$key] = $request[$key];
        }
        $data->save();

        return $data;
    }

    public function selectList($request = [], $with = [], $select = [], $fieldValue = 'id', $fieldTitle = 'name')
    {
        $data = $this->model->with($with)->where(function ($query) use ($request) {
            if (! empty($request['idsAllowed'])) {
                $query->whereIn('id', $request['idsAllowed']);
            }
            if (! empty($request['company_id'])) {
                $query->where('company_id', $request['company_id']);
            }
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

    public function getValidationsErrorMessages($user_id)
    {
        // Recuperar y mostrar los errores almacenados en Redis
        $errorListKey = "list:glosas_import_errors_{$user_id}";
        $errors = Redis::lrange($errorListKey, 0, -1); // Obtener todos los elementos de la lista
        $errorsFormatted = [];

        if (! empty($errors)) {
            foreach ($errors as $index => $errorJson) {
                $errorsFormatted[] = json_decode($errorJson, true); // Decodificar el JSON
            }

        }

        return $errorsFormatted;
    }
}
