<?php

namespace App\Repositories;

use App\Enums\Assignment\StatusAssignmentEnum;
use App\Helpers\Constants;
use App\Models\Assignment;
use App\Models\AssignmentBatche;
use App\Models\InvoiceAudit;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Third;
use App\QueryBuilder\Sort\DynamicConcatSort;
use Illuminate\Support\Facades\DB;
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

                if (!empty($request['company_id'])) {
                    $query->where('company_id', $request['company_id']);
                }
            })
            ->where(function ($query) use ($request) {
                if (isset($request['searchQueryInfinite']) && !empty($request['searchQueryInfinite'])) {
                    $query->orWhere('name', 'like', '%' . $request['searchQueryInfinite'] . '%');
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
                        $query->whereNotIn('status', [StatusAssignmentEnum::ASSIGNMENT_EST_003]);
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

                    if (!empty($request['company_id'])) {
                        $query->where('company_id', $request['company_id']);
                    }

                    if (!empty($request['user_id'])) {
                        $query->whereHas('assignments', function ($subQuery) use ($request) {
                            if (!empty($request['user_id'])) {
                                $subQuery->where('user_id', $request['user_id']);
                            }
                        });
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
                    'assignments as count_invoice_total' => function ($query) use ($request) {
                        $query->where('user_id', $request['user_id']);
                        $query->where('assignment_batch_id', $request['assignment_batch_id']);
                    },
                    'assignments as count_invoice_pending' => function ($query) use ($request) {
                        $query->where('assignment_batch_id', $request['assignment_batch_id']);
                        $query->where('user_id', $request['user_id']);

                        $query->where(function ($subQuery) {
                            $subQuery->whereNotIn('status', [StatusAssignmentEnum::ASSIGNMENT_EST_003]);
                        });
                    },
                    'assignments as count_invoice_finish' => function ($query) use ($request) {
                        $query->where('user_id', $request['user_id']);
                        $query->where('assignment_batch_id', $request['assignment_batch_id']);
                        $query->where('status', StatusAssignmentEnum::ASSIGNMENT_EST_003);
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
                        $subQuery->where('user_id', $request['user_id']);
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

        $userId = $request['user_id'] ?? null;

        // 1) Armar el CASE WHEN SQL dinámicamente
        if ($userId) {
            // Status por usuario específico
            $caseStatus = "
                CASE
                  WHEN EXISTS (
                    SELECT 1
                      FROM assignments
                     WHERE assignments.invoice_audit_id = invoice_audits.id
                       AND assignments.status <> '" . StatusAssignmentEnum::ASSIGNMENT_EST_003->value . "'
                       AND assignments.user_id = '{$userId}'
                  ) THEN 'pending'
                  ELSE 'finished'
                END
                AS status
            ";
        } else {
            // Status global (cualquier assignment pendiente, sin filtrar por usuario)
            $caseStatus = "
                CASE
                  WHEN EXISTS (
                    SELECT 1
                      FROM assignments
                     WHERE assignments.invoice_audit_id = invoice_audits.id
                       AND assignments.status <> '" . StatusAssignmentEnum::ASSIGNMENT_EST_003->value . "'
                  ) THEN 'pending'
                  ELSE 'finished'
                END
                AS status
            ";
        }

        return $this->cacheService->remember($cacheKey, function () use ($request, $caseStatus) {
            $query = QueryBuilder::for(InvoiceAudit::query())
                ->select('*')
                ->addSelect([
                    'status' => DB::raw($caseStatus),
                    'user_names' => Assignment::selectRaw('CONCAT(users.name, \' \', COALESCE(users.surname, \'\'))')
                        ->join('users', 'users.id', '=', 'assignments.user_id')
                        ->whereColumn('invoice_audit_id', 'invoice_audits.id')
                        ->when(!empty($request['assignment_batch_id']), function ($subQuery) use ($request) {
                            $subQuery->where('assignment_batch_id', $request['assignment_batch_id']);
                        })
                        ->when(!empty($request['company_id']), function ($subQuery) use ($request) {
                            $subQuery->where('assignments.company_id', $request['company_id']);
                        })
                        ->when(!empty($request['user_id']), function ($subQuery) use ($request) {
                            $subQuery->where('user_id', $request['user_id']);
                        })
                        ->when(!empty($request['third_id']), function ($subQuery) use ($request) {
                            $subQuery->whereHas('invoiceAudit', function ($query2) use ($request) {
                                $query2->where('third_id', $request['third_id']);
                            });
                        }),
                ])
                ->withCount(['patients', 'services', 'glosas as count_glosas'])
                ->withSum('services as value_glosa', 'value_glosa')
                ->withSum('services as value_approved', 'value_approved')
                ->allowedFilters([
                    AllowedFilter::callback('inputGeneral', function ($query, $value) {
                        // $query->orWhere(function ($subQuery) use ($value) {
                        //     $number = preg_replace('/[\$\s\.,]/', '', $value);
                        //     $subQuery->orHaving('total_value_services', 'like', $number)
                        //     ->orHaving('value_glosa',        'like', $number);
                        // });
                        $query->where(function ($subQuery) use ($value) {
                            $subQuery->orWhere('invoice_number', 'like', "%$value%");
                            // $subQuery->orWhere('status', 'like', "%$value%");
                            $subQuery->orWhere(function ($subQuery) use ($value) {
                                $normalizedValue = preg_replace('/[\$\s\.,]/', '', $value);
                                $subQuery->orWhere('total_value', 'like', "%$normalizedValue%");
                            });

                            // Búsqueda por nombres y apellidos de usuarios
                            $subQuery->orWhereHas('assignment.user', function ($userQuery) use ($value) {
                                $userQuery->where(function ($q) use ($value) {
                                    $q->where('name', 'like', "%$value%")
                                        ->orWhere('surname', 'like', "%$value%")
                                        ->orWhereRaw("CONCAT(name, ' ', COALESCE(surname, '')) LIKE ?", ["%$value%"]);
                                });
                            });
                        });
                    }),
                ])
                ->allowedSorts([
                    'invoice_number',
                    'value_glosa',
                    'value_approved',
                    'total_value',
                    'user_names',
                ])->where(column: function ($query) use ($request) {
                    if (!empty($request['company_id'])) {
                        $query->where('company_id', $request['company_id']);
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
                        $query->whereHas('assignment', function ($subQuery) use ($request) {
                            if (!empty($request['user_id'])) {
                                $subQuery->where('user_id', $request['user_id']);
                            }
                        });
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

                    if (!empty($request['invoice_audit_id'])) {
                        $query->where('invoice_audit_id', $request['invoice_audit_id']);
                    }
                    if (!empty($request['patient_id'])) {
                        $query->where('patient_id', $request['patient_id']);
                    }
                    if (!empty($request['company_id'])) {
                        $query->where('company_id', $request['company_id']);
                    }

                    if (!empty($request['user_id'])) {
                        $query->whereHas('invoice_audit.assignment', function ($subQuery) use ($request) {
                            if (!empty($request['user_id'])) {
                                $subQuery->where('user_id', $request['user_id']);
                            }
                        });
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
                ->withCount(['glosas as count_glosas'])
                ->withSum('services as value_glosa', 'value_glosa')
                ->withSum('services as value_approved', 'value_approved')
                ->withSum('services as total_value', 'total_value')
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
                    'value_glosa',
                    'value_approved',
                    'total_value',
                ])->where(function ($query) use ($request) {

                    if (!empty($request['invoice_audit_id'])) {
                        $query->where('invoice_audit_id', $request['invoice_audit_id']);
                    }

                    if (!empty($request['user_id'])) {
                        $query->whereHas('invoice_audit.assignment', function ($subQuery) use ($request) {
                            if (!empty($request['user_id'])) {
                                $subQuery->where('user_id', $request['user_id']);
                            }
                        });
                    }
                })
                ->paginate(request()->perPage ?? Constants::ITEMS_PER_PAGE);

            return $query;
        }, Constants::REDIS_TTL);
    }

    public function store(array $request)
    {
        $request = $this->clearNull($request);

        if (!empty($request['id'])) {
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
            if (!empty($request['idsAllowed'])) {
                $query->whereIn('id', $request['idsAllowed']);
            }
            if (!empty($request['company_id'])) {
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

        if (!empty($errors)) {
            foreach ($errors as $index => $errorJson) {
                $errorsFormatted[] = json_decode($errorJson, true); // Decodificar el JSON

            }
        }

        return $errorsFormatted;
    }
}
