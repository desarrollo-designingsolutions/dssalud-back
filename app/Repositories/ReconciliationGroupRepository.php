<?php

namespace App\Repositories;

use App\Helpers\Constants;
use App\Models\ReconciliationGroup;
use App\QueryBuilder\Sort\RelatedTableSort;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class ReconciliationGroupRepository extends BaseRepository
{
    public function __construct(ReconciliationGroup $modelo)
    {
        parent::__construct($modelo);
    }

    public function paginate($request = [])
    {
        $cacheKey = $this->cacheService->generateKey("{$this->model->getTable()}_paginate", $request, 'string');

        return $this->cacheService->remember($cacheKey, function () use ($request) {
            $query = QueryBuilder::for($this->model->query())
                ->select(['reconciliation_groups.id', 'reconciliation_groups.company_id', 'reconciliation_groups.name', 'reconciliation_groups.third_id'])

                ->allowedFilters([

                    AllowedFilter::callback('inputGeneral', function ($query, $value) {
                        $query->where(function ($subQuery) use ($value) {
                            $subQuery->orWhere('reconciliation_groups.name', 'like', "%$value%");

                            $subQuery->orWhereHas('third', function ($thirdQuery) use ($value) {
                                $thirdQuery->where(function ($q) use ($value) {
                                    $q->where('name', 'like', "%$value%");
                                });
                            });
                            $subQuery->orWhereHas('third', function ($thirdQuery) use ($value) {
                                $thirdQuery->where(function ($q) use ($value) {
                                    $q->where('nit', 'like', "%$value%");
                                });
                            });
                        });
                    }),
                ])
                ->allowedSorts([
                    'name',
                    AllowedSort::custom('third_nit', new RelatedTableSort(
                        'reconciliation_groups',
                        'thirds',
                        'nit',
                        'third_id',
                    )),
                    AllowedSort::custom('third_name', new RelatedTableSort(
                        'reconciliation_groups',
                        'thirds',
                        'name',
                        'third_id',
                    )),
                ])
                ->where(function ($query) use ($request) {
                    if (! empty($request['company_id'])) {
                        $query->where('reconciliation_groups.company_id', $request['company_id']);
                    }
                });

            if (empty($request['typeData'])) {
                $query = $query->paginate(request()->perPage ?? Constants::ITEMS_PER_PAGE);
            } else {
                $query = $query->get();
            }

            return $query;
        }, Constants::REDIS_TTL);
    }

    public function list($request = [], $with = [], $select = ['*'], $idsAllowed = [], $idsNotAllowed = [])
    {
        $cacheKey = $this->cacheService->generateKey("{$this->model->getTable()}_list", $request, 'string');

        return $this->cacheService->remember($cacheKey, function () use ($request, $with) {

            $data = $this->model->with($with)->where(function ($query) {})
                ->where(function ($query) use ($request) {

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

    public function paginateConciliation($request = [])
    {
        $cacheKey = $this->cacheService->generateKey("{$this->model->getTable()}_paginateConciliation", $request, 'string');

        return $this->cacheService->remember($cacheKey, function () use ($request) {
            $query = QueryBuilder::for($this->model->query())
                ->select(['reconciliation_groups.id', 'reconciliation_groups.company_id', 'reconciliation_groups.name', 'reconciliation_groups.third_id'])

                ->allowedFilters([

                    AllowedFilter::callback('inputGeneral', function ($query, $value) {
                        $query->where(function ($subQuery) use ($value) {
                            $subQuery->orWhere('reconciliation_groups.name', 'like', "%$value%");

                            $subQuery->orWhereHas('third', function ($thirdQuery) use ($value) {
                                $thirdQuery->where(function ($q) use ($value) {
                                    $q->where('name', 'like', "%$value%");
                                });
                            });
                            $subQuery->orWhereHas('third', function ($thirdQuery) use ($value) {
                                $thirdQuery->where(function ($q) use ($value) {
                                    $q->where('nit', 'like', "%$value%");
                                });
                            });
                        });
                    }),
                ])
                ->allowedSorts([
                    'name',
                    AllowedSort::custom('third_nit', new RelatedTableSort(
                        'reconciliation_groups',
                        'thirds',
                        'nit',
                        'third_id',
                    )),
                    AllowedSort::custom('third_name', new RelatedTableSort(
                        'reconciliation_groups',
                        'thirds',
                        'name',
                        'third_id',
                    )),
                ])
                ->where(function ($query) use ($request) {
                    if (! empty($request['company_id'])) {
                        $query->where('reconciliation_groups.company_id', $request['company_id']);
                    }
                });

            if (empty($request['typeData'])) {
                $query = $query->paginate(request()->perPage ?? Constants::ITEMS_PER_PAGE);
            } else {
                $query = $query->get();
            }

            return $query;
        }, Constants::REDIS_TTL);
    }
}
