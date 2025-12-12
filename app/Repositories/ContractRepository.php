<?php

namespace App\Repositories;

use App\Helpers\Constants;
use App\Models\Contract;
use App\QueryBuilder\Sort\RelatedTableSort;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class ContractRepository extends BaseRepository
{
    public function __construct(Contract $modelo)
    {
        parent::__construct($modelo);
    }

    public function paginate($request = [])
    {
        $cacheKey = $this->cacheService->generateKey("{$this->model->getTable()}_paginate", $request, 'string');

        return $this->cacheService->remember($cacheKey, function () use ($request) {
            $query = QueryBuilder::for($this->model->query())
                ->with(['third'])
                ->select([
                    'contracts.id',
                    'contracts.name',
                    'contracts.company_id',
                    'contracts.third_id',
                ])
                ->allowedFilters([
                    AllowedFilter::callback('inputGeneral', function ($queryX, $value) {
                        $queryX->where(function ($query) use ($value) {
                            $query->orWhere('name', 'like', "%$value%");

                            $query->orWhereHas('third', function ($subQuery) use ($value) {
                                $subQuery->where('thirds.name', 'like', "%$value%");
                            });
                        });
                    }),
                ])
                ->allowedSorts([
                    'name',
                    AllowedSort::custom('third_name', new RelatedTableSort('contracts', 'thirds', 'name', 'third_id')),

                ])
                ->where(function ($query) use ($request) {
                    if (!empty($request['company_id'])) {
                        $query->where('contracts.company_id', $request['company_id']);
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

    public function list($request = [], $with = [], $select = ['*'])
    {

        $cacheKey = $this->cacheService->generateKey("{$this->model->getTable()}_list", $request, 'string');

        return $this->cacheService->remember($cacheKey, function () use ($request, $select, $with) {

            $data = $this->model->select($select)->with($with)->where(function ($query) use ($request) {
                if (!empty($request['company_id'])) {
                    $query->where('company_id', $request['company_id']);
                }
                if (!empty($request['is_active'])) {
                    $query->where('is_active', $request['is_active']);
                }
                if (!empty($request['third_id'])) {
                    $query->where('third_id', $request['third_id']);
                }
            })->where(function ($query) use ($request) {
                if (isset($request['searchQueryInfinite']) && !empty($request['searchQueryInfinite'])) {
                    $query->orWhere('name', 'like', '%' . $request['searchQueryInfinite'] . '%');
                }
            });

            if (isset($request['sortBy'])) {
                $sortBy = json_decode($request['sortBy'], 1);
                foreach ($sortBy as $key => $value) {
                    $data = $data->orderBy($value['key'], $value['order']);
                }
            }

            if (empty($request['typeData'])) {
                $data = $data->paginate($request['perPage'] ?? Constants::ITEMS_PER_PAGE);
            } else {
                $data = $data->get();
            }

            return $data;
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

    public function selectList($request = [], $with = [], $select = [], $fieldValue = 'id', $fieldTitle = 'name')
    {
        $data = $this->model->with($with)->where(function ($query) use ($request) {
            if (!empty($request['idsAllowed'])) {
                $query->whereIn('id', $request['idsAllowed']);
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

    public function searchOne($request = [], $with = [], $select = ['*'])
    {
        $data = $this->model->select($select)->with($with)->where(function ($query) use ($request) {
            if (!empty($request['company_id'])) {
                $query->where('company_id', $request['company_id']);
            }
        });

        $data = $data->first();

        return $data;
    }
}
