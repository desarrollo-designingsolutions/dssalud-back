<?php

namespace App\Repositories;

use App\Enums\Filing\StatusFilingInvoiceEnum;
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
            if (!empty($request['invoice_number'])) {
                $query->where('invoice_number', $request['invoice_number']);
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
                $query->whereHas("filing", function ($subQuery) use ($request) {
                    $subQuery->where("company_id", $request['company_id']);
                });
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
            if (!empty($request['filing_id'])) {
                $query->where("filing_id", $request['filing_id']);
            }
        });

        $data = $data->count();

        return $data;
    }

    public function validInvoiceNumbers($filing_id)
    {
        return $this->model->where("filing_id", $filing_id)->where('status', StatusFilingInvoiceEnum::FILINGINVOICE_EST_001)->pluck("invoice_number")->toArray();
    }

    function getValidationsErrorMessages($id)
    {
        $data = $this->model::find($id);

        // Inicializar un array para almacenar los mensajes de error
        $errorMessages = [];

        // Definir las validaciones
        $validations = [
            ['key' => 'validationXml', 'type' => 'XML'],
            ['key' => 'validationTxt', 'type' => 'TXT'],
            // Agrega más objetos de validación aquí según sea necesario
        ];

        // Iterar sobre cada validación
        foreach ($validations as $validation) {
            if (isset($data[$validation['key']])) {
                $parsedData = json_decode($data[$validation['key']], true);
                foreach ($parsedData as $message) {
                    $message['type'] = $validation['type']; // Agregar la propiedad "type" al mensaje de error
                    $errorMessages[] = $message; // Agregar el mensaje al array de errorMessages
                }
            }
        }

        return $errorMessages;
    }
}
