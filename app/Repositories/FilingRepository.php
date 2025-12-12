<?php

namespace App\Repositories;

use App\Enums\Filing\StatusFilingEnum;
use App\Enums\Filing\StatusFilingInvoiceEnum;
use App\Enums\Filing\TypeFilingEnum;
use App\Helpers\Constants;
use App\Models\Filing;
use App\Models\FilingInvoice;
use App\Models\User;
use App\QueryBuilder\Filters\QueryFilters;
use App\QueryBuilder\Sort\RelatedTableSort;
use App\Traits\FilterManager;
use Illuminate\Support\Facades\Log;
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

        return $this->cacheService->remember($cacheKey, function () use ($filter, $request) {

            $query = QueryBuilder::for($this->model->query())
                ->with(['contract:id,name', 'user:id,name,surname'])
                ->select(['filings.id', 'contract_id', 'type', 'status', 'sumVr', 'filings.company_id', 'filings.user_id'])
                ->withCount(['filingInvoicePreRadicated'])
                ->defaultSort('-created_at')
                ->allowedFilters([
                    'status',

                    AllowedFilter::callback('inputGeneral', function ($query, $value) {
                        $query->where(function ($query) use ($value) {

                            $query->orWhereHas('user', function ($subQuery) use ($value) {
                                $subQuery->whereRaw("CONCAT(name, ' ', surname) LIKE ?", ["%{$value}%"]);
                            });
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
                        });
                    }),
                ])
                ->allowedSorts([
                    'type',
                    'status',
                    'sumVr',
                    'filing_invoice_pre_radicated_count',
                    'created_at',
                    AllowedSort::custom('contract_name', new RelatedTableSort(
                        'filings',
                        'contracts',
                        'name',
                        'contract_id',
                    )),
                    AllowedSort::custom('user_full_name', new RelatedTableSort('filings', 'users', 'name', 'user_id')),

                ]);

            if (isset($filter['filing_invoice_pre_radicated_count']) && is_numeric($filter['filing_invoice_pre_radicated_count'])) {
                $query->having('filing_invoice_pre_radicated_count', '=', $filter['filing_invoice_pre_radicated_count']);
            }

            if (!empty($request['company_id'])) {
                $query = $query->where('filings.company_id', $request['company_id']);
            }

            if (!empty($request['user_id'])) {
                $query = $query->whereHas('contract.third', function ($query) use ($request) {
                    $query->whereHas('users', function ($query) use ($request) {
                        $query->where('users.id', $request['user_id']);
                    });
                });
            }

            $query = $query->paginate(request()->perPage ?? Constants::ITEMS_PER_PAGE);

            return $query;
        }, Constants::REDIS_TTL);
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

    public function getValidationsErrorMessages($id)
    {
        $data = $this->model::find($id);

        // Inicializar un array para almacenar los mensajes de error
        $errorMessages = [];

        $type = 'JSON';
        if ($data->type == TypeFilingEnum::FILING_TYPE_001) {
            $type = 'TXT';
        }

        // Definir las validaciones
        $validations = [
            ['key' => 'validationZip', 'type' => 'ZIP'],
            ['key' => 'validationTxt', 'type' => $type],
            // Agrega más objetos de validación aquí según sea necesario
        ];

        // Iterar sobre cada validación
        foreach ($validations as $validation) {

            if (isset($data[$validation['key']])) {
                $parsedData = json_decode($data[$validation['key']], true);

                if (isset($parsedData)) {
                    $elementos = $parsedData;

                    if ($data->type == TypeFilingEnum::FILING_TYPE_002) {
                        $elementos = $parsedData['errorMessages'];
                    }

                    foreach ($elementos as $message) {
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
            'has_invoices' => $data->filingInvoice->count() > 0,
        ];
    }

    public function getAllValidation($filing_id)
    {
        $filingInvoices = FilingInvoice::where('filing_id', $filing_id)->select(['validationXml', 'validationTxt'])->get();

        // Inicializar un array para almacenar los mensajes de error
        $errorMessages = [];

        // Definir las validaciones
        $validations = [
            ['key' => 'validationXml', 'type' => 'XML'],
            ['key' => 'validationTxt', 'type' => 'TXT'],
            // Agrega más objetos de validación aquí según sea necesario
        ];

        // Iterar sobre cada validación
        foreach ($filingInvoices as $filingInvoice) {
            foreach ($validations as $validation) {
                if (isset($filingInvoice[$validation['key']])) {
                    $parsedData = json_decode($filingInvoice[$validation['key']], true);
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
        $filingInvoices = FilingInvoice::where('filing_id', $filing_id)->where('status', StatusFilingInvoiceEnum::FILINGINVOICE_EST_001)->count();

        return $filingInvoices;
    }

    public function getCountFilingInvoiceWithOutSupports($filing_id)
    {
        $filingInvoices = FilingInvoice::where('filing_id', $filing_id)->where('status', StatusFilingInvoiceEnum::FILINGINVOICE_EST_001)->get();

        $countSupports = 0;
        foreach ($filingInvoices as $key => $value) {
            if ($value->files_count == 0) {
                $countSupports++;
            }
        }

        return $countSupports;
    }

    public function changeStatusFilingInvoicePreRadicated($filing_id)
    {
        $filingInvoices = FilingInvoice::where('filing_id', $filing_id)->where('status', StatusFilingInvoiceEnum::FILINGINVOICE_EST_001)->get();

        foreach ($filingInvoices as $key => $value) {
            $value->status = StatusFilingInvoiceEnum::FILINGINVOICE_EST_002;
            $value->save();
        }

        // ->update([
        //     'status' => StatusFilingInvoiceEnum::FILINGINVOICE_EST_002,
        // ]);

        return $filingInvoices;
    }
}
