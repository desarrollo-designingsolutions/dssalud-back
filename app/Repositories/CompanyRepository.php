<?php

namespace App\Repositories;

use App\Helpers\Constants;
use App\Models\Company;
use App\QueryBuilder\Filters\QueryFilters;
use App\QueryBuilder\Sort\IsActiveSort;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class CompanyRepository extends BaseRepository
{
    public function __construct(Company $modelo)
    {
        parent::__construct($modelo);
    }

    public function paginate($request = [])
    {
        $cacheKey = $this->cacheService->generateKey("{$this->model->getTable()}_paginate", $request, 'string');

        return $this->cacheService->remember($cacheKey, function () {

            $query = QueryBuilder::for($this->model->query())
                ->select(['id', 'name', 'nit', 'email', 'phone', 'is_active', 'logo'])
                ->allowedFilters([
                    'name',
                    'is_active',
                    AllowedFilter::callback('inputGeneral', function ($query, $value) {
                        $query->orWhere('name', 'like', "%$value%");
                        $query->orWhere('nit', 'like', "%$value%");
                        $query->orWhere('email', 'like', "%$value%");
                        $query->orWhere('phone', 'like', "%$value%");
                        QueryFilters::filterByText($query, $value, 'is_active', [
                            'activo' => 1,
                            'inactivo' => 0,
                        ]);
                    }),
                ])
                ->allowedSorts([
                    'name',
                    'nit',
                    'email',
                    'phone',
                    AllowedSort::custom('is_active', new IsActiveSort),
                ])
                ->paginate(request()->perPage ?? Constants::ITEMS_PER_PAGE);

            return $query;
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
