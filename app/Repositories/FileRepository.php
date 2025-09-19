<?php

namespace App\Repositories;

use App\Helpers\Constants;
use App\Models\File;
use App\QueryBuilder\Sort\RelatedTableSort;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class FileRepository extends BaseRepository
{
    public function __construct(File $modelo)
    {
        parent::__construct($modelo);
    }

    public function list($request = [], $with = [], $select = ['*'])
    {
        $data = $this->model->select($select)
            ->with($with)
            ->where(function ($query) use ($request) {
                filterComponent($query, $request);

                if (! empty($request['fileable_id'])) {
                    $query->where('fileable_id', $request['fileable_id']);
                }
                if (! empty($request['fileable_type'])) {
                    $query->where('fileable_type', 'App\\Models\\'.$request['fileable_type']);
                }
            });
        if (empty($request['typeData'])) {
            $data = $data->paginate($request['perPage'] ?? 10);
        } else {
            $data = $data->get();
        }

        return $data;
    }

    public function paginate($request = [])
    {
        $cacheKey = $this->cacheService->generateKey("{$this->model->getTable()}_paginate", $request, 'string');

        return $this->cacheService->remember($cacheKey, function () use ($request) {

            $query = QueryBuilder::for($this->model->query())
            ->select(["files.id","files.created_at","files.filename","files.support_type_id","files.pathname"])
                ->allowedFilters([
                    AllowedFilter::callback('inputGeneral', function ($query, $value) {
                        $query->where(function ($query) use ($value) {
                            $query->orWhere('filename', 'like', "%$value%");

                            $query->orWhereHas('supportType', function ($subQuery) use ($value) {
                                $subQuery->where('name', 'like', "%$value%");
                            });
                        });
                    }),
                ])
                ->allowedSorts([
                    'created_at',
                    'filename',
                    AllowedSort::custom('support_type_name', new RelatedTableSort(
                        'files',
                        'support_types',
                        'name',
                        'support_type_id',
                    )),
                ])
                ->where(function ($query) use ($request) {
                    if (isset($request['company_id']) && ! empty($request['company_id'])) {
                        $query->where('company_id', $request['company_id']);
                    }
                    if (! empty($request['fileable_id'])) {
                        $query->where('fileable_id', $request['fileable_id']);
                    }
                    if (! empty($request['fileable_type'])) {
                        $query->where('fileable_type', 'App\\Models\\'.$request['fileable_type']);
                    }
                })
                ->paginate(request()->perPage ?? Constants::ITEMS_PER_PAGE);

            return $query;
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
}
