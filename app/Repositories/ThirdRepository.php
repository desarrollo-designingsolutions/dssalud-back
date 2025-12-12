<?php

namespace App\Repositories;

use App\Helpers\Constants;
use App\Models\Third;

class ThirdRepository extends BaseRepository
{
    public function __construct(Third $modelo)
    {
        parent::__construct($modelo);
    }

    public function list($request = [], $with = [], $select = ['*'], $idsAllowed = [], $idsNotAllowed = [])
    {
        $cacheKey = $this->cacheService->generateKey("{$this->model->getTable()}_list", $request, 'string');

        return $this->cacheService->remember($cacheKey, function () use ($request, $with) {

            $data = $this->model->with($with)->where(function ($query) {})
                ->where(function ($query) use ($request) {
                    filterComponent($query, $request);

                    if (!empty($request['company_id'])) {
                        $query->where('company_id', $request['company_id']);
                    }
                    if (!empty($request['user_id'])) {
                        $query->whereHas('users', function ($q) use ($request) {
                            $q->where('users.id', $request['user_id']);
                        });
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

    public function getTotalThirdsInAssignedAudits($request = [])
    {
        $data = $this->model::query()
            ->with([
                'invoiceAudits' => function ($query) use ($request) {
                    $query->whereHas('assignment', function ($subQuery) use ($request) {
                        if (!empty($request['user_id'])) {
                            $subQuery->where('user_id', $request['user_id']);
                        }
                        if (!empty($request['assignment_batch_id'])) {
                            $subQuery->where('assignment_batch_id', $request['assignment_batch_id']);
                        }
                    });
                },
            ])
            ->whereHas('invoiceAudits', function ($subQuery) use ($request) {
                $subQuery->whereHas('assignment', function ($subQuery2) use ($request) {
                    if (!empty($request['user_id'])) {
                        $subQuery2->where('user_id', $request['user_id']);
                    }
                    if (!empty($request['assignment_batch_id'])) {
                        $subQuery2->where('assignment_batch_id', $request['assignment_batch_id']);
                    }
                });
            })
            ->where(
                function ($query) use ($request) {
                    if (!empty($request['third_id'])) {
                        $query->where('id', $request['third_id']);
                    }
                    if (!empty($request['company_id'])) {
                        $query->where('company_id', $request['company_id']);
                    }
                }
            )
            ->count();

        return $data;
    }
}
