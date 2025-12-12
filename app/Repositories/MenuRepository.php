<?php

namespace App\Repositories;

use App\Helpers\Constants;
use App\Models\Menu;

class MenuRepository extends BaseRepository
{
    public function __construct(Menu $modelo)
    {
        parent::__construct($modelo);
    }

    public function list($request = [], $with = [], $idsAllowed = [])
    {
        $data = $this->model->with($with)->orderBy('order', 'ASC')->where(function ($query) use ($request, $idsAllowed) {
            if (! empty($request['permissions'])) {
                $query->whereIn('requiredPermission', $request['permissions']);
            }
            if (! empty($request['title'])) {
                $query->where('title', $request['title']);
            }
            if (! empty($request['to'])) {
                $query->where('to', $request['to']);
            }
            if (! empty($request['icon'])) {
                $query->where('icon', $request['icon']);
            }
            if (! empty($request['father_null'])) {
                $query->whereNull('father');
            }
            if (! empty($request['withPermissions'])) {
                $query->whereHas('permissions');
            }

            // idsAllowed
            if (count($idsAllowed) > 0) {
                $query->whereIn('id', $idsAllowed);
            }
            if (! empty($request['idsAllowed']) && count($request['idsAllowed']) > 0) {
                $query->whereIn('id', $request['idsAllowed']);
            }
            // filterComponent($query, $request);
        });

        if (empty($request['typeData'])) {
            $data = $data->paginate($request['perPage'] ?? Constants::ITEMS_PER_PAGE);
        } else {
            $data = $data->get();
        }

        return $data;
    }

    public function store($request)
    {
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
}
