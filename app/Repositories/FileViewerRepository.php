<?php

namespace App\Repositories;

use App\Models\FileViewer;
use App\Traits\AuditMap;
use Illuminate\Support\Facades\Auth;

class FileViewerRepository extends BaseRepository
{
    use AuditMap;

    public function __construct(FileViewer $modelo)
    {
        parent::__construct($modelo);
    }

    public function list($request = [], $with = [], $select = ['*'], $order = [])
    {
        $data = $this->model->select($select)
            ->with($with)
            ->where(function ($query) use ($request) {
                filterComponent($query, $request);

                if (! empty($request['name'])) {
                    $query->where('name', 'like', '%'.$request['name'].'%');
                }

                // idsAllowed
                if (! empty($request['idsAllowed']) && count($request['idsAllowed']) > 0) {
                    $query->whereIn('id', $request['idsAllowed']);
                }

                // idsNotAllowed
                if (! empty($request['idsNotAllowed']) && count($request['idsNotAllowed']) > 0) {
                    $query->whereNotIn('id', $request['idsNotAllowed']);
                }

                if (! empty($request['company_id'])) {
                    $query->where('company_id', $request['company_id']);
                }

            });

        if (count($order) > 0) {
            foreach ($order as $key => $value) {
                $data = $data->orderBy($value['field'], $value['type']);
            }
        }
        if (empty($request['typeData'])) {
            $data = $data->paginate($request['perPage'] ?? 10);
        } else {
            $data = $data->get();
        }

        return $data;
    }

    public function store($request, $id = null, $withCompany = true)
    {
        $validatedData = $this->clearNull($request);

        $idToUse = $id ?? ($validatedData['id'] ?? null);

        if ($idToUse) {
            $data = $this->model->find($idToUse);
        } else {
            $data = $this->model::newModelInstance();
            if ($withCompany) {
                $data->company_id = auth()->user()->company_id;
            }
        }

        foreach ($request as $key => $value) {
            $data[$key] = is_array($request[$key]) ? $request[$key]['value'] : $request[$key];
        }

        if (! empty($validatedData['password'])) {
            $data->password = $validatedData['password'];
        } else {
            unset($data->password);
        }

        $data->save();

        return $data;
    }

    public function register($request)
    {
        $data = $this->model;

        foreach ($request as $key => $value) {
            $data[$key] = $request[$key];
        }

        $data->save();

        return $data;
    }

    public function findByEmail($email)
    {
        return $this->model::where('email', $email)->first();
    }

    public function selectList($request = [], $with = [], $select = [], $fieldValue = 'id', $fieldTitle = 'name')
    {
        $data = $this->model->with($with)->where(function ($query) use ($request) {
            if (! empty($request['idsAllowed'])) {
                $query->whereIn('id', $request['idsAllowed']);
            }

            $query->where('is_active', true);
            $query->where('company_id', auth()->user()->company_id);
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

    public function countData($request = [])
    {
        $data = $this->model->where(function ($query) use ($request) {
            if (! empty($request['status_id'])) {
                $query->where('status_id', $request['status_id']);
            }

            // rol_in_id
            if (isset($request['rol_in_id']) && count($request['rol_in_id']) > 0) {
                $query->whereIn('role_id', $request['rol_in_id']);
            }
            // divisio_in_id
            if (isset($request['division_in_id']) && count($request['division_in_id']) > 0) {
                $query->whereIn('branch_division_id', $request['division_in_id']);
            }
            $query->where('company_id', Auth::user()->company_id);
            $query->where('role_id', '!=', 1);
        })->count();

        return $data;
    }

    public function timeLine($request = [])
    {
        $typeData = $request['typeData'] ?? 'all';

        // Cargar los datos con relaciones, incluyendo los eliminados
        $data = $this->model::find($request['auditable_id']);

        if (! $data) {
            return collect(); // Si no hay datos, devolver una colección vacía
        }

        // Obtener todos los audits (del modelo principal y de los comentarios)
        $audits = $this->getAllAudits($data);

        // Aplicar el mapeo de columnas dinámicamente según el modelo de cada audit
        $this->applyColumnMappingToAudits($audits);

        // Ordenar por 'created_at' en orden descendente
        $audits = $audits->sortByDesc('created_at');

        // Devolver el resultado según el tipo de datos solicitado
        return $typeData === 'count' ? $audits->count() : $audits;
    }

    /**
     * Obtener todos los audits del modelo principal y de sus comentarios.
     */
    protected function getAllAudits($data)
    {
        $audits = $data->audits;
        $relations = [];

        // Cargar los audits de las relaciones, incluyendo los eliminados (soft deleted)
        foreach ($relations as $relation) {
            foreach ($data->$relation()->withTrashed()->get() as $element) {
                $audits = $audits->merge($element->audits);
            }
        }

        return $audits;
    }
}
