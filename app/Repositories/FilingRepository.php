<?php

namespace App\Repositories;

use App\Enums\Filing\StatusFilingEnum;
use App\Enums\Filing\StatusFilingInvoiceEnum;
use App\Enums\Filing\TypeFilingEnum;
use App\Helpers\Constants;
use App\Models\Filing;
use App\Models\FilingInvoice;
use App\QueryBuilder\Filters\QueryFilters;
use App\QueryBuilder\Sort\RelatedTableSort;
use App\Traits\FilterManager;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class FilingRepository extends BaseRepository
{
    use FilterManager;

    public function __construct(Filing $modelo)
    {
        parent::__construct($modelo);
    }

    public function paginate($request = [])
    {

        $data = request();
        $filter['filing_invoice_pre_radicated_count'] = isset($data['filter']['filing_invoice_pre_radicated_count']) ? $data['filter']['filing_invoice_pre_radicated_count'] : null;

        $this->removeInvalidFilters(['filing_invoice_pre_radicated_count']);

        $cacheKey = $this->cacheService->generateKey("{$this->model->getTable()}_paginate", $request, 'string');

        return $this->cacheService->remember($cacheKey, function () use ($filter) {

            $query = QueryBuilder::for($this->model->query())
                ->with(['contract:id,name'])
                ->select(['filings.id', 'contract_id', 'type', 'status', 'sumVr'])
                ->withCount(['filingInvoicePreRadicated'])
                ->allowedFilters([
                    'status',

                    AllowedFilter::callback('inputGeneral', function ($query, $value) {
                        $query->orWhereHas('contract', function ($subQuery) use ($value) {
                            $subQuery->where('name', 'like', "%$value%");
                        });

                        QueryFilters::filterByText($query, $value, 'type', [
                            TypeFilingEnum::FILING_TYPE_001->description() => TypeFilingEnum::FILING_TYPE_001,
                            TypeFilingEnum::FILING_TYPE_002->description() => TypeFilingEnum::FILING_TYPE_002,
                        ]);
                        QueryFilters::filterByText($query, $value, 'status', [
                            StatusFilingEnum::FILING_EST_008->description() => StatusFilingEnum::FILING_EST_008,
                            StatusFilingEnum::FILING_EST_009->description() => StatusFilingEnum::FILING_EST_009,
                        ]);

                        $query->orWhere(function ($subQuery) use ($value) {
                            $normalizedValue = preg_replace('/[\$\s\.,]/', '', $value);
                            $subQuery->orWhere('sumVr', 'like', "%$normalizedValue%");
                        });
                    }),
                ])
                ->allowedSorts([
                    'type',
                    'status',
                    'sumVr',
                    'filing_invoice_pre_radicated_count',
                    AllowedSort::custom('contract_name', new RelatedTableSort(
                        'filings',
                        'contracts',
                        'name',
                        'contract_id',
                    )),
                ]);

            if (isset($filter['filing_invoice_pre_radicated_count']) && is_numeric($filter['filing_invoice_pre_radicated_count'])) {
                $query->having('filing_invoice_pre_radicated_count', '=', $filter['filing_invoice_pre_radicated_count']);
            }
            $query = $query->paginate(request()->perPage ?? Constants::ITEMS_PER_PAGE);

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

    public function getValidationsErrorMessages($id)
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
            'errorMessages' => $errorMessages,
            'validationTxt' => json_decode($data->validationTxt, 1),
            'validationZip' => json_decode($data->validationZip, 1),
        ];
    }

    public function getAllValidation($filing_id)
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
            'status' => StatusFilingInvoiceEnum::FILINGINVOICE_EST_002,
        ]);

        return $fileInvoices;
    }
}
