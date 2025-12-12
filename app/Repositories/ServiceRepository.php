<?php

namespace App\Repositories;

use App\Helpers\Constants;
use App\Models\Service;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ServiceRepository extends BaseRepository
{
    public function __construct(Service $modelo)
    {
        parent::__construct($modelo);
    }

    public function paginate($request = [])
    {
        $cacheKey = $this->cacheService->generateKey("{$this->model->getTable()}_paginate", $request, 'string');

        return $this->cacheService->remember($cacheKey, function () use ($request) {

            $query = QueryBuilder::for($this->model->query())
                ->allowedFilters([
                    AllowedFilter::callback('inputGeneral', function ($query, $value) {}),
                ])
                ->allowedSorts([])
                ->where(function ($query) use ($request) {
                    if (isset($request['service_id']) && ! empty($request['service_id'])) {
                        $query->orWhere('service_id', $request['service_id']);
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

    public function getServicesToImportGlosas($request = [])
    {
        $data = $this->model::query()
            ->with([
                'patient' => function ($query) use ($request) {
                    if (! empty($request['patient_id'])) {
                        $query->where('id', $request['patient_id']);
                    }
                },
                'invoice_audit' => function ($query) use ($request) {
                    if (! empty($request['invoice_audit_id'])) {
                        $query->where('id', $request['invoice_audit_id']);
                    }

                    if (! empty($request['third_id'])) {
                        $query->whereHas('third', function ($subQuery) use ($request) {
                            if (! empty($request['third_id'])) {
                                $subQuery->where('id', $request['third_id']);
                            }
                        });
                    }

                    $query->whereHas('assignment.assignmentBatche', function ($subQuery) use ($request) {
                        if (! empty($request['assignment_batch_id'])) {
                            $subQuery->where('assignment_batch_id', $request['assignment_batch_id']);
                        }
                    });

                },

            ])
            ->where(function ($query) use ($request) {

                if (! empty($request['invoice_audit_id'])) {
                    $query->where('invoice_audit_id', $request['invoice_audit_id']);
                }
                if (! empty($request['patient_id'])) {
                    $query->where('patient_id', $request['patient_id']);
                }

                if (! empty($request['third_id'])) {
                    $query->whereHas('invoice_audit', function ($subQuery) use ($request) {
                        if (! empty($request['third_id'])) {
                            $subQuery->where('third_id', $request['third_id']);
                        }
                    });
                }

                if (! empty($request['user_id'])) {
                    $query->whereHas('invoice_audit.assignment', function ($subQuery) use ($request) {
                        if (! empty($request['user_id'])) {
                            $subQuery->where('user_id', $request['user_id']);
                        }
                    });
                }

                if (! empty($request['assignment_batch_id'])) {

                    $query->whereHas('invoice_audit.assignment.assignmentBatche', function ($subQuery) use ($request) {
                        if (! empty($request['assignment_batch_id'])) {
                            $subQuery->where('assignment_batch_id', $request['assignment_batch_id']);
                        }
                    });
                }
            })
            ->get();

        return $data;
    }
}
