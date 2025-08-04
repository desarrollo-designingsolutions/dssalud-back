<?php

namespace App\Repositories;

use App\Helpers\Constants;
use App\Models\Country;

class CountryRepository extends BaseRepository
{
    public function __construct(Country $modelo)
    {
        parent::__construct($modelo);
    }

    public function list($request = [], $with = [], $select = ['*'], $idsAllowed = [])
    {
        $cacheKey = $this->cacheService->generateKey("{$this->model->getTable()}_list", $request, 'string');

        return $this->cacheService->remember($cacheKey, function () use ($request, $select, $with, $idsAllowed) {

            $data = $this->model->select($select)
                ->with($with)
                ->where(function ($query) use ($request, $idsAllowed) {
                    if (! empty($request['name'])) {
                        $query->where('id', 'like', '%'.$request['name'].'%');
                    }
                    if (count($idsAllowed) > 0) {
                        $query->whereIn('id', $idsAllowed);
                    }
                    if (! empty($request['idsAllowed']) && count($request['idsAllowed']) > 0) {
                        $query->whereIn('id', $request['idsAllowed']);
                    }
                })
                ->where(function ($query) use ($request) {
                    if (! empty($request['searchQueryInfinite'])) {
                        $query->orWhere('name', 'like', '%'.$request['searchQueryInfinite'].'%');
                    }
                });
            if (empty($request['typeData'])) {
                $data = $data->paginate($request['perPage'] ?? 10);
            } else {
                $data = $data->get();
            }

            return $data;
        }, Constants::REDIS_TTL);
    }
}
