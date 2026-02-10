<?php

namespace App\Repositories;

use App\Helpers\Constants;
use App\Models\SupportType;

class SupportTypeRepository extends BaseRepository
{
    public function __construct(SupportType $modelo)
    {
        parent::__construct($modelo);
    }

    public function list($request = [], $with = [], $idsAllowed = [])
    {
        $cacheKey = $this->cacheService->generateKey("{$this->model->getTable()}_list", $request, 'string');

        return $this->cacheService->remember($cacheKey, function () use ($request, $with) {

            $data = $this->model->with($with)->where(function ($query) {})
                ->where(function ($query) use ($request) {
                    filterComponent($query, $request);

                    if (! empty($request['company_id'])) {
                        $query->where('company_id', $request['company_id']);
                    }
                    if (! empty($request['module'])) {
                        $query->where('module', $request['module']);
                    }
                })->where(function ($query) use ($request) {
                    if (isset($request['searchQueryInfinite']) && ! empty($request['searchQueryInfinite'])) {
                        $query->orWhere('code', 'like', '%'.$request['searchQueryInfinite'].'%');
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

    public function searchOne($request = [], $with = [], $idsAllowed = [])
    {
        // Construcción de la consulta
        $data = $this->model->with($with)->where(function ($query) use ($request) {
            if (! empty($request['id'])) {
                $query->where('id', $request['id']);
            }
        });

        // Obtener el primer resultado
        $data = $data->first();

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

    public function validSupportCodes()
    {
        $data = $this->model->get()->pluck('code')->toArray();

        return $data;
    }
}
