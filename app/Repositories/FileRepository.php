<?php

namespace App\Repositories;

use App\Models\File;
use Carbon\Carbon;

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

                if (!empty($request['fileable_id'])) {
                    $query->where('fileable_id', $request['fileable_id']);
                }
                if (!empty($request['fileable_type'])) {
                    $query->where('fileable_type', 'App\\Models\\' . $request['fileable_type']);
                }

                if (isset($request['searchQuery']['relationsGeneral']) && count($request['searchQuery']['relationsGeneral']) > 0) {

                    $search = $request['searchQuery']['generalSearch'];

                    // Recursivamente filtrar todos los elementos que contienen '|custom'
                    $customColumns = [];

                    array_walk_recursive($request['searchQuery']['relationsGeneral'], function ($value) use (&$customColumns) {
                        // Verificar si el valor contiene '|custom'
                        if (strpos($value, '|custom') !== false) {
                            // Eliminar '|custom' y agregar el valor al array
                            $customColumns[] = str_replace('|custom', '', $value);
                        }
                    });

                    foreach ($customColumns as $key => $value) {
                        if ($value == 'created_at' && !empty($search)) {
                            $date = Carbon::createFromFormat('d/m/Y', $search);
                            $query->orWhereDate('created_at', '=', $date);
                        }
                    }
                }
            });
        if (empty($request['typeData'])) {
            $data = $data->paginate($request['perPage'] ?? 10);
        } else {
            $data = $data->get();
        }

        return $data;
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
}
