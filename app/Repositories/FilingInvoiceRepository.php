<?php

namespace App\Repositories;

use App\Enums\Filing\StatusFillingInvoiceEnum;
use App\Enums\StatusInvoiceEnum;
use App\Models\FilingInvoice;

class FilingInvoiceRepository extends BaseRepository
{
    public function __construct(FilingInvoice $modelo)
    {
        parent::__construct($modelo);
    }

    public function list($request = [], $with = [], $idsAllowed = [])
    {
        $data = $this->model->with($with)->where(function ($query) {})
            ->where(function ($query) use ($request) {
                filterComponent($query, $request);

                if (!empty($request['company_id'])) {
                    $query->whereHas("filing", function ($subQuery) use ($request) {
                        $subQuery->where("company_id", $request['company_id']);
                    });
                }
                if (!empty($request['filing_id'])) {
                    $query->where("filing_id", $request['filing_id']);
                }



                // if (isset($request['searchQuery']['relationsGeneral']) && count($request['searchQuery']['relationsGeneral']) > 0) {

                //     $search = $request['searchQuery']['generalSearch'];

                //     // Recursivamente filtrar todos los elementos que contienen '|custom'
                //     $customColumns = [];

                //     array_walk_recursive($request['searchQuery']['relationsGeneral'], function ($value) use (&$customColumns) {
                //         // Verificar si el valor contiene '|custom'
                //         if (strpos($value, '|custom') !== false) {
                //             // Eliminar '|custom' y agregar el valor al array
                //             $customColumns[] = str_replace('|custom', '', $value);
                //         }
                //     });

                //     foreach ($customColumns as $key => $value) {
                //         if ($value == 'status' && !empty($search)) {
                //             // Obtener todos los casos del enum
                //             $status = StatusFillingInvoiceEnum::cases();

                //             // Buscar el caso que tenga la descripción que coincida con $search
                //             $matchingCase = null;
                //             foreach ($status as $case) {
                //                 if (stripos($case->description(), $search) !== false) {
                //                     $matchingCase = $case;
                //                     break;  // Si encuentras una coincidencia, salimos del ciclo
                //                 }
                //             }

                //             // Si encontramos un caso, se aplica el filtro en el query
                //             if ($matchingCase) {
                //                 // var_dump($matchingCase);
                //                 $query->where('status', $matchingCase->value);  // Usamos el valor del caso
                //             }
                //         }

                //     }
                // }


            });

        $data = $data->orderBy('id', 'desc');
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


    public function searchOne($request = [], $with = [], $idsAllowed = [])
    {
        // Construcción de la consulta
        $data = $this->model->with($with)->where(function ($query) use ($request) {
            if (!empty($request['id'])) {
                $query->where('id', $request['id']);
            }
        });

        // Obtener el primer resultado
        $data = $data->first();

        return $data;
    }

    public function selectList($request = [], $with = [], $select = [], $fieldValue = 'id', $fieldTitle = 'description')
    {
        $data = $this->model->with($with)->where(function ($query) use ($request) {
            if (!empty($request['idsAllowed'])) {
                $query->whereIn('id', $request['idsAllowed']);
            }
            if (!empty($request['company_id'])) {
                $query->where('company_id', $request['company_id']);
            }
            $query->where('viewable', '1');
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
            if (!empty($request['company_id'])) {
                $query->whereHas("filing", function ($subQuery) use ($request) {
                    $subQuery->where("company_id", $request['company_id']);
                });
            }
            if (!empty($request['status'])) {
                $query->where("status", $request['status']);
            }
        });

        $data = $data->count();

        return $data;
    }
}
