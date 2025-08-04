<?php

namespace App\Repositories;

use App\Helpers\Constants;
use App\Models\Schedule;
use App\QueryBuilder\Filters\DataSelectFilter;
use App\QueryBuilder\Filters\DateRangeFilter;
use App\QueryBuilder\Sort\RelatedModelSort;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class ScheduleRepository extends BaseRepository
{
    public function __construct(Schedule $modelo)
    {
        parent::__construct($modelo);
    }

    public function paginate($request = [])
    {
        $cacheKey = $this->cacheService->generateKey("{$this->model->getTable()}_paginate", $request, 'string');

        return $this->cacheService->remember($cacheKey, function () use ($request) {
            $query = QueryBuilder::for($this->model->query())
                ->select([
                    'id',
                    'title',
                    'company_id',
                ])->allowedFilters([
                    AllowedFilter::callback('inputGeneral', function ($queryX, $value) {
                        $queryX->where(function ($query) use ($value) {
                            $query->orWhere('title', 'like', "%$value%");
                        });
                    }),
                ])
                ->allowedSorts([
                    'title',
                ])
                ->where(function ($query) use ($request) {
                    if (! empty($request['company_id'])) {
                        $query->where('company_id', $request['company_id']);
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

    public function paginateAgenda($request = [])
    {
        $cacheKey = $this->cacheService->generateKey("{$this->model->getTable()}_paginateAgenda", $request, 'string');

        $query = QueryBuilder::for($this->model->query())
            ->with(['scheduleable'])
            // ->select(['schedules.id', 'schedules.company_id', 'schedules.title', 'schedules.start_date', 'schedules.start_hour'])
            ->allowedFilters([

                AllowedFilter::custom('response_status', new DataSelectFilter('scheduleable')),

                AllowedFilter::custom('start_date', new DateRangeFilter),

                AllowedFilter::custom('response_date', new DateRangeFilter('scheduleable')),

                AllowedFilter::callback('inputGeneral', function ($queryX, $value) {
                    $queryX->where(function ($query) use ($value) {
                        $query->where(function ($subQuery) use ($value) {
                            $subQuery->orWhere('title', 'like', "%$value%");
                        });
                        $query->orWhereHas('scheduleable.third', function ($subQuery) use ($value) {
                            $subQuery->where('name', 'like', "%$value%");
                            $subQuery->orWhere('nit', 'like', "%$value%");
                        });
                        $query->orWhereHas('scheduleable.reconciliation_group', function ($subQuery) use ($value) {
                            $subQuery->where('name', 'like', "%$value%");
                        });
                        $query->orWhereHas('scheduleable.user', function ($subQuery) use ($value) {
                            $subQuery->whereRaw("CONCAT_WS(' ', name, surname) LIKE ?", ["%{$value}%"]);
                        });
                    });
                }),
            ])
            ->allowedSorts([
                'title',
                'response_date',
                'response_status',
                AllowedSort::custom('third_name', new RelatedModelSort(
                    relationship: 'scheduleable.third',
                    relatedTable: 'thirds',
                    relatedColumn: 'name',
                    morphType: 'scheduleable_type',
                    morphClass: 'App\Models\ScheduleConciliation'
                )),
                AllowedSort::custom('user_name', new RelatedModelSort(
                    relationship: 'scheduleable.user',
                    relatedTable: 'users',
                    relatedColumn: 'name',
                    morphType: 'scheduleable_type',
                    morphClass: 'App\Models\ScheduleConciliation'
                )),
                AllowedSort::custom('reconciliation_group_name', new RelatedModelSort(
                    relationship: 'scheduleable.reconciliation_group',
                    relatedTable: 'reconciliation_groups',
                    relatedColumn: 'name',
                    morphType: 'scheduleable_type',
                    morphClass: 'App\Models\ScheduleConciliation'
                )),
            ])
            ->where(function ($query) use ($request) {
                if (! empty($request['company_id'])) {
                    $query->where('schedules.company_id', $request['company_id']);
                }
            });

        if (empty($request['typeData'])) {
            $query = $query->paginate(request()->perPage ?? Constants::ITEMS_PER_PAGE);
        } else {
            $query = $query->get();
        }

        return $query;
    }

    public function list($request = [], $with = [], $select = ['*'])
    {
        $cacheKey = $this->cacheService->generateKey("{$this->model->getTable()}_list", $request, 'string');

        return $this->cacheService->remember($cacheKey, function () use ($request, $select, $with) {

            $data = $this->model->select($select)->with($with)->where(function ($query) use ($request) {
                filterComponent($query, $request);

                if (! empty($request['company_id'])) {
                    $query->where('company_id', $request['company_id']);
                }
                if (! empty($request['is_active'])) {
                    $query->where('is_active', $request['is_active']);
                }
            })->where(function ($query) use ($request) {
                if (isset($request['searchQueryInfinite']) && ! empty($request['searchQueryInfinite'])) {
                    $query->orWhere('name', 'like', '%'.$request['searchQueryInfinite'].'%');
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

    public function selectList($request = [], $with = [], $select = [], $fieldValue = 'id', $fieldTitle = 'name')
    {
        $data = $this->model->with($with)->where(function ($query) use ($request) {
            if (! empty($request['idsAllowed'])) {
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
            if (! empty($request['company_id'])) {
                $query->where('company_id', $request['company_id']);
            }
        });

        $data = $data->first();

        return $data;
    }

    public function getEventsCalendar($request = [])
    {
        $data = $this->model->where(function ($query) use ($request) {

            if (! empty($request['dateStart']) && ! empty($request['dateFinal'])) {
                $query->where(function ($query2) use ($request) {
                    // La tarea debe empezar antes o en la fecha final proporcionada
                    // y debe terminar después o en la fecha inicial proporcionada
                    $query2->whereDate('start_date', '<=', $request['dateFinal'])
                        ->whereDate('end_date', '>=', $request['dateStart']);
                });
            }

            // Si solo se proporciona 'dateStart', filtrar las tareas que empiezan antes de esta fecha
            if (! empty($request['dateStart']) && empty($request['dateFinal'])) {
                $query->whereDate('start_date', '<=', $request['dateStart']);
            }

            // Si solo se proporciona 'dateFinal', filtrar las tareas que terminan después de esta fecha
            if (empty($request['dateStart']) && ! empty($request['dateFinal'])) {
                $query->whereDate('end_date', '>=', $request['dateFinal']);
            }

            // if (! empty($request['user_id'])) {
            //     $query->where('user_id', $request['user_id']);
            // }
            // if (! empty($request['users_ids'])) {
            //     $users_ids = json_decode($request['users_ids']);
            //     $query->whereIn('user_id', $users_ids);
            // }
            if (! empty($request['company_id'])) {
                $query->where('company_id', $request['company_id']);
            }
        })->get();

        return $data;
    }
}
