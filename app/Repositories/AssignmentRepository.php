<?php

namespace App\Repositories;

use App\Enums\Assignment\StatusAssignmentEnum;
use App\Helpers\Constants;
use App\Models\Assignment;
use App\Models\InvoiceAudit;
use App\Models\Patient;
use App\Models\Third;
use App\QueryBuilder\Sort\DynamicConcatSort;
use App\Traits\AuditMap;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;
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
                    'assignments as count_invoice_total' => function ($query) use ($request) {
                        $query->where('assignment_batch_id', $request['assignment_batch_id']);
                    },
                    'assignments as count_invoice_pending' => function ($query) use ($request) {
                        $query->where('assignment_batch_id', $request['assignment_batch_id']);

                        $query->where(function ($subQuery) {
                            $subQuery->whereNotIn('status', [StatusAssignmentEnum::ASSIGNMENT_EST_003]);
                        });
                    },
                    'assignments as count_invoice_finish' => function ($query) use ($request) {
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

                    'user_names' => Assignment::selectRaw('GROUP_CONCAT(DISTINCT CONCAT(users.name, \' \', COALESCE(users.surname, \'\')) SEPARATOR ", ")')
                        ->join('users', 'users.id', '=', 'assignments.user_id')
                        ->when(!empty($request['assignment_batch_id']), function ($subQuery) use ($request) {
                            $subQuery->where('assignment_batch_id', $request['assignment_batch_id']);
                        })
                        ->when(!empty($request['company_id']), function ($subQuery) use ($request) {
                            $subQuery->where('assignments.company_id', $request['company_id']);
                        })
                        ->whereHas('invoiceAudit', function ($subQuery) {
                            $subQuery->whereColumn('third_id', 'thirds.id');
                        }),
                    'count_users' => Assignment::selectRaw('COUNT(DISTINCT assignments.user_id)')
                        ->join('users', 'users.id', '=', 'assignments.user_id')
                        ->when(!empty($request['assignment_batch_id']), function ($subQuery) use ($request) {
                            $subQuery->where('assignment_batch_id', $request['assignment_batch_id']);
                        })
                        ->when(!empty($request['company_id']), function ($subQuery) use ($request) {
                            $subQuery->where('assignments.company_id', $request['company_id']);
                        })
                        ->whereHas('invoiceAudit', function ($subQuery) {
                            $subQuery->whereColumn('third_id', 'thirds.id');
                        }),
                ])
                ->allowedFilters([
                    AllowedFilter::callback('inputGeneral', function ($query, $value) use ($request) {
                        $query->where(function ($subQuery) use ($value, $request) {
                            $subQuery->orWhere('nit', 'like', "%$value%");
                            $subQuery->orWhere('name', 'like', "%$value%");
                            // Búsqueda en users.name o users.surname
                            Assignment::scopeUserNamesForThirds($subQuery, $value, $request);
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
                    'user_names',
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
                ->withCount(['patients', 'services', 'glosas as count_glosas'])
                ->withSum('services as value_glosa', 'value_glosa')
                ->withSum('services as value_approved', 'value_approved')
                ->addSelect([
                    'user_names' => Assignment::selectRaw('GROUP_CONCAT(DISTINCT CONCAT(users.name, \' \', COALESCE(users.surname, \'\')) SEPARATOR ", ")')
                        ->join('users', 'users.id', '=', 'assignments.user_id')
                        ->whereColumn('invoice_audit_id', 'invoice_audits.id')
                        ->when(!empty($request['assignment_batch_id']), function ($subQuery) use ($request) {
                            $subQuery->where('assignment_batch_id', $request['assignment_batch_id']);
                        })
                        ->when(!empty($request['company_id']), function ($subQuery) use ($request) {
                            $subQuery->where('assignments.company_id', $request['company_id']);
                        })
                        ->when(!empty($request['third_id']), function ($subQuery) use ($request) {
                            $subQuery->whereHas('invoiceAudit', function ($query2) use ($request) {
                                $query2->where('third_id', $request['third_id']);
                            });
                        }),
                    'count_users' => Assignment::selectRaw('COUNT(DISTINCT assignments.user_id)')
                        ->join('users', 'users.id', '=', 'assignments.user_id')
                        ->whereColumn('invoice_audit_id', 'invoice_audits.id')
                        ->when(!empty($request['assignment_batch_id']), function ($subQuery) use ($request) {
                            $subQuery->where('assignment_batch_id', $request['assignment_batch_id']);
                        })
                        ->when(!empty($request['company_id']), function ($subQuery) use ($request) {
                            $subQuery->where('assignments.company_id', $request['company_id']);
                        })
                        ->when(!empty($request['third_id']), function ($subQuery) use ($request) {
                            $subQuery->whereHas('invoiceAudit', function ($query2) use ($request) {
                                $query2->where('third_id', $request['third_id']);
                            });
                        }),
                ])
                ->allowedFilters([
                    AllowedFilter::callback('inputGeneral', function ($query, $value) {
                        $query->where(function ($subQuery) use ($value) {
                            $subQuery->orWhere('invoice_number', 'like', "%$value%");
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
                    'user_names',
                    'value_glosa',
                    'value_approved',
                ])->where(function ($query) use ($request) {

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
                ->withCount(['glosas as count_glosas'])
                ->withSum('services as value_glosa', 'value_glosa')
                ->withSum('services as value_approved', 'value_approved')
                ->withSum('services as total_value', 'total_value')
                ->allowedFilters([
                    AllowedFilter::callback('inputGeneral', function ($query, $value) {
                        $query->where(function ($subQuery) use ($value) {
                            $subQuery->orWhereRaw("CONCAT(patients.first_name, ' ', patients.second_name, ' ', patients.first_surname, ' ', patients.second_surname) LIKE ?", ["%{$value}%"]);

                            $subQuery->orWhere('identification_number', 'like', "%$value%");
                            $subQuery->orWhere('gender', 'like', "%$value%");
                            $subQuery->orWhere('total_value', 'like', "%$value%");
                        });
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

                if (!empty($request['status_diff_to'])) {
                    $query->whereNotIn('status', $request['status_diff_to']);
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

    public function changeStatusAssigmentMasive($request)
    {
        $assignment = $this->model::where(function ($query) use ($request) {

            if (!empty($request['assignments_ids'])) {
                $query->whereIn('id', $request['assignments_ids']);
            }

            // COMPAÑIA
            if (!empty($request['company_id'])) {
                $query->where('company_id', $request['company_id']);
            }
            // USUARIO LOGEUADO
            if (!empty($request['user_id'])) {
                $query->where('user_id', $request['user_id']);
            }

            // TERCEROS
            if (!empty($request['thirds_ids'])) {
                $query->whereHas('invoiceAudit', function ($subQuery) use ($request) {
                    $subQuery->whereIn('third_id', $request['thirds_ids']);
                });
            }
            // PACIENTES
            if (!empty($request['patients_ids'])) {
                $query->whereHas('invoiceAudit.patients', function ($subQuery) use ($request) {
                    $subQuery->whereIn('id', $request['patients_ids']);
                });
            }

            // PAQUETE
            if (!empty($request['assignment_batch_id'])) {
                $query->where('assignment_batch_id', $request['assignment_batch_id']);
            }
            if (!empty($request['assignments_batchs_ids'])) {
                $query->whereIn('assignment_batch_id', $request['assignments_batchs_ids']);
            }

            // FACTURAS
            if (!empty($request['invoice_audit_id'])) {
                $query->where('invoice_audit_id', $request['invoice_audit_id']);
            }
            if (!empty($request['invoices_audits_ids'])) {
                $query->whereIn('invoice_audit_id', $request['invoices_audits_ids']);
            }

            $query->where('status', '!=', StatusAssignmentEnum::ASSIGNMENT_EST_003->value);
        });

        // logMessage($assignment->pluck("id"));
        $assignment->update([
            'status' => StatusAssignmentEnum::ASSIGNMENT_EST_003,
        ]);

        return $assignment;
    }

    public function changeStatusAssigmentMasiveReturn($request)
    {
        $assignment = $this->model::where(function ($query) use ($request) {

            if (!empty($request['assignments_ids'])) {
                $query->whereIn('id', $request['assignments_ids']);
            }

            // COMPAÑIA
            if (!empty($request['company_id'])) {
                $query->where('company_id', $request['company_id']);
            }
            // USUARIO LOGEUADO
            if (!empty($request['user_id'])) {
                $query->where('user_id', $request['user_id']);
            }

            // TERCEROS
            if (!empty($request['thirds_ids'])) {
                $query->whereHas('invoiceAudit', function ($subQuery) use ($request) {
                    $subQuery->whereIn('third_id', $request['thirds_ids']);
                });
            }
            // PACIENTES
            if (!empty($request['patients_ids'])) {
                $query->whereHas('invoiceAudit.patients', function ($subQuery) use ($request) {
                    $subQuery->whereIn('id', $request['patients_ids']);
                });
            }

            // PAQUETE
            if (!empty($request['assignment_batch_id'])) {
                $query->where('assignment_batch_id', $request['assignment_batch_id']);
            }
            if (!empty($request['assignments_batchs_ids'])) {
                $query->whereIn('assignment_batch_id', $request['assignments_batchs_ids']);
            }

            // FACTURAS
            if (!empty($request['invoice_audit_id'])) {
                $query->where('invoice_audit_id', $request['invoice_audit_id']);
            }
            if (!empty($request['invoices_audits_ids'])) {
                $query->whereIn('invoice_audit_id', $request['invoices_audits_ids']);
            }

            $query->where('status', '!=', StatusAssignmentEnum::ASSIGNMENT_EST_004->value);
        });

        // logMessage($assignment->pluck("id"));
        $assignment->update([
            'status' => StatusAssignmentEnum::ASSIGNMENT_EST_004,
        ]);

        return $assignment;
    }

    public function searchOne($request = [], $with = [], $idsAllowed = [])
    {
        // Construcción de la consulta
        $data = $this->model->with($with)->where(function ($query) use ($request) {
            if (!empty($request['id'])) {
                $query->where('id', $request['id']);
            }

            if (!empty($request['company_id'])) {
                $query->where('company_id', $request['company_id']);
            }

            if (!empty($request['assignment_batch_id'])) {
                $query->where('assignment_batch_id', $request['assignment_batch_id']);
            }

            if (!empty($request['user_id'])) {
                $query->where('user_id', $request['user_id']);
            }

            if (!empty($request['invoice_audit_id'])) {
                $query->where('invoice_audit_id', $request['invoice_audit_id']);
            }

            if (!empty($request['third_id'])) {
                $query->whereHas('invoiceAudit', function ($subQuery) use ($request) {
                    $subQuery->where('third_id', $request['third_id']);
                });
            }
        });

        // Obtener el primer resultado
        $data = $data->first();

        return $data;
    }

    public function getValidationsErrorMessages($user_id)
    {
        // Recuperar y mostrar los errores almacenados en Redis
        $errorListKey = "string:assignment_import_errors_{$user_id}";
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
