<?php

namespace App\Repositories;

use App\Enums\Filing\StatusFilingInvoiceEnum;
use App\Models\Filing;
use App\Models\FilingInvoice;

class FilingRepository extends BaseRepository
{
    public function __construct(Filing $modelo)
    {
        parent::__construct($modelo);
    }

    public function list($request = [], $with = [], $withCount = [])
    {
        $data = $this->model->with($with)->withCount($withCount)->where(function ($query) {})
            ->where(function ($query) use ($request) {
                filterComponent($query, $request);

                if (!empty($request['type'])) {
                    $query->where('type', $request['type']);
                }

                if (!empty($request['company_id'])) {
                    $query->where("company_id", $request['company_id']);
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
        });

        // Obtener el primer resultado
        $data = $data->first();

        return $data;
    }


    function getValidationsErrorMessages($id)
    {
        $data = $this->model::find($id);

        // Inicializar un array para almacenar los mensajes de error
        $errorMessages = [];

        // Definir las validaciones
        $validations = [
            ['key' => 'validationZip', 'type' => 'ZIP'],
            ['key' => 'validationTxt', 'type' => 'TXT'],
            // Agrega más objetos de validación aquí según sea necesario
        ];

        // Iterar sobre cada validación
        foreach ($validations as $validation) {
            if (isset($data[$validation['key']])) {
                $parsedData = json_decode($data[$validation['key']], true);
                if (isset($parsedData['errorMessages'])) {
                    foreach ($parsedData['errorMessages'] as $message) {
                        $message['type'] = $validation['type']; // Agregar la propiedad "type" al mensaje de error
                        $errorMessages[] = $message; // Agregar el mensaje al array de errorMessages
                    }
                }
            }
        }

        return [
            "errorMessages" => $errorMessages,
            "validationTxt" => json_decode($data->validationTxt, 1),
            "validationZip" => json_decode($data->validationZip, 1),
        ];
    }

    function getAllValidation($filing_id)
    {
        $fileInvoices = FilingInvoice::where('filing_id', $filing_id)->select(['validationXml', 'validationTxt'])->get();

        // Inicializar un array para almacenar los mensajes de error
        $errorMessages = [];

        // Definir las validaciones
        $validations = [
            ['key' => 'validationXml', 'type' => 'XML'],
            ['key' => 'validationTxt', 'type' => 'TXT'],
            // Agrega más objetos de validación aquí según sea necesario
        ];

        // Iterar sobre cada validación
        foreach ($fileInvoices as $fileInvoice) {
            foreach ($validations as $validation) {
                if (isset($fileInvoice[$validation['key']])) {
                    $parsedData = json_decode($fileInvoice[$validation['key']], true);
                    foreach ($parsedData as $message) {
                        $message['type'] = $validation['type']; // Agregar la propiedad "type" al mensaje de error
                        $errorMessages[] = $message; // Agregar el mensaje al array de errorMessages
                    }
                }
            }
        }

        return $errorMessages;
    }

    public function getCountFilingInvoicePreRadicated($filing_id)
    {
        $fileInvoices = FilingInvoice::where('filing_id', $filing_id)->where('status', StatusFilingInvoiceEnum::FILINGINVOICE_EST_001)->count();
        return $fileInvoices;
    }

    public function changeStatusFilingInvoicePreRadicated($filing_id)
    {
        $fileInvoices = FilingInvoice::where('filing_id', $filing_id)->where('status', StatusFilingInvoiceEnum::FILINGINVOICE_EST_001)->update([
            'status' => StatusFilingInvoiceEnum::FILINGINVOICE_EST_002
        ]);

        return $fileInvoices;
    }
}
