<?php

namespace App\Repositories;

use App\Helpers\Constants;
use App\Models\Glosa;
use App\QueryBuilder\Sort\DynamicConcatSort;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class GlosaRepository extends BaseRepository
{
    public function __construct(Glosa $modelo)
    {
        parent::__construct($modelo);
    }

    public function paginate($request = [])
    {
        $cacheKey = $this->cacheService->generateKey("{$this->model->getTable()}_paginate", $request, 'string');

        return $this->cacheService->remember($cacheKey, function () use ($request) {

            $query = QueryBuilder::for($this->model->query())
                ->select('glosas.*')
                // ->join("users", "users.id", "=", "user_id")
                // ->join("services", "services.id", "=", "service_id")
                ->allowedFilters([
                    AllowedFilter::callback('inputGeneral', function ($query, $value) {
                        $query->where(function ($query) use ($value) {
                            $query->orWhere('glosas.observation', 'like', "%$value%");

                            $query->orWhere(function ($subQuery) use ($value) {
                                $normalizedValue = preg_replace('/[\$\s\.,]/', '', $value);
                                $subQuery->where('glosa_value', 'like', "%$normalizedValue%");
                            });
                            $query->orWhereHas('code_glosa', function ($subQuery) use ($value) {
                                $subQuery->where('description', 'like', "%$value%");
                            });
                            $query->orWhereHas('service', function ($subQuery) use ($value) {
                                $subQuery->where('description', 'like', "%$value%");
                            });
                            $query->orWhereHas('user', function ($subQuery) use ($value) {
                                $subQuery->whereRaw("CONCAT(users.name, ' ', users.surname) LIKE ?", ["%{$value}%"]);
                            });
                        });
                    }),
                ])
                ->allowedSorts([
                    'observation',
                    'glosa_value',
                    AllowedSort::custom('user_full_name', new DynamicConcatSort("users.name, ' ', users.surname")),
                    AllowedSort::custom('service_description', new DynamicConcatSort('services.description')),
                ])
                ->where(function ($query) use ($request) {
                    if (isset($request['service_id']) && ! empty($request['service_id'])) {
                        $query->where('service_id', $request['service_id']);
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
}
