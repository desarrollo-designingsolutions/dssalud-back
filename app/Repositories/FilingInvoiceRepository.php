<?php

namespace App\Repositories;

use App\Enums\Filing\StatusFilingInvoiceEnum;
use App\Enums\Filing\TypeFilingEnum;
use App\Helpers\Constants;
use App\Models\FilingInvoice;
use App\QueryBuilder\Filters\DataSelectFilter;
use App\QueryBuilder\Filters\DateRangeFilter;
use App\QueryBuilder\Filters\QueryFilters;
use App\QueryBuilder\Sort\StatusOldSort;
use App\Traits\FilterManager;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class FilingInvoiceRepository extends BaseRepository
{
    use FilterManager;

    public function __construct(FilingInvoice $modelo)
    {
        parent::__construct($modelo);
    }

    public function paginate($request = [])
    {
        $customTypes = [
            ['value' => 'FILINGINVOICE_EST_001', 'title' => StatusFilingInvoiceEnum::FILINGINVOICE_EST_001->description()],
            ['value' => 'FILINGINVOICE_EST_002', 'title' => StatusFilingInvoiceEnum::FILINGINVOICE_EST_002->description()],
            ['value' => 'FILINGINVOICE_EST_003', 'title' => StatusFilingInvoiceEnum::FILINGINVOICE_EST_003->description()],
            ['value' => 'FILINGINVOICE_EST_004', 'title' => StatusFilingInvoiceEnum::FILINGINVOICE_EST_004->description()],
            ['value' => 'FILINGINVOICE_EST_005', 'title' => StatusFilingInvoiceEnum::FILINGINVOICE_EST_005->description()],
        ];

        $data = request();
        $filter['files_count'] = isset($data['filter']['files_count']) ? $data['filter']['files_count'] : null;

        $this->removeInvalidFilters(['files_count']);

        $cacheKey = $this->cacheService->generateKey("{$this->model->getTable()}_paginate", $request, 'string');

        return $this->cacheService->remember($cacheKey, function () use ($filter, $request, $customTypes) {

            $query = QueryBuilder::for($this->model->query())
                ->select(['filing_invoices.id', 'invoice_number', 'users_count', 'case_number', 'sumVr', 'status', 'status_xml', 'date', 'path_xml'])
                ->withCount(['files'])
                ->allowedFilters([
                    AllowedFilter::callback('inputGeneral', function ($query, $value) {
                        $query->where(function ($query) use ($value) {
                            $query->orWhere('invoice_number', 'like', "%$value%");
                            $query->orWhere('users_count', 'like', "%$value%");
                            $query->orWhere('case_number', 'like', "%$value%");

                            $query->orWhere(function ($subQuery) use ($value) {
                                $normalizedValue = preg_replace('/[\$\s\.,]/', '', $value);
                                $subQuery->orWhere('sumVr', 'like', "%$normalizedValue%");
                            });

                            QueryFilters::filterByText($query, $value, 'status', [
                                StatusFilingInvoiceEnum::FILINGINVOICE_EST_001->description() => StatusFilingInvoiceEnum::FILINGINVOICE_EST_001,
                                StatusFilingInvoiceEnum::FILINGINVOICE_EST_002->description() => StatusFilingInvoiceEnum::FILINGINVOICE_EST_002,
                            ]);
                            QueryFilters::filterByText($query, $value, 'status_xml', [
                                StatusFilingInvoiceEnum::FILINGINVOICE_EST_003->description() => StatusFilingInvoiceEnum::FILINGINVOICE_EST_003,
                                StatusFilingInvoiceEnum::FILINGINVOICE_EST_004->description() => StatusFilingInvoiceEnum::FILINGINVOICE_EST_004,
                            ]);
                        });
                    }),
                    AllowedFilter::custom('status', new DataSelectFilter),
                    AllowedFilter::custom('status_xml', new DataSelectFilter),
                    AllowedFilter::custom('date', new DateRangeFilter),
                ])
                ->allowedSorts([
                    'invoice_number',
                    'users_count',
                    'case_number',
                    'sumVr',
                    'files_count',
                    'date',
                    AllowedSort::custom('status', new StatusOldSort($customTypes)),
                ]);

            if (!empty($request['filing_id'])) {
                $query = $query->where('filing_id', $request['filing_id']);
            }

            if (isset($filter['files_count']) && is_numeric($filter['files_count'])) {
                $query->having('files_count', '=', $filter['files_count']);
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
            if (!empty($request['invoice_number'])) {
                $query->where('invoice_number', $request['invoice_number']);
            }
            if (!empty($request['filing_id'])) {
                $query->where('filing_id', $request['filing_id']);
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
                $query->whereHas('filing', function ($subQuery) use ($request) {
                    $subQuery->where('company_id', $request['company_id']);
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
                $query->whereHas('filing', function ($subQuery) use ($request) {
                    $subQuery->where('company_id', $request['company_id']);
                });
            }
            if (!empty($request['status'])) {
                $query->where('status', $request['status']);
            }
            if (!empty($request['filing_id'])) {
                $query->where('filing_id', $request['filing_id']);
            }
        });

        $data = $data->count();

        return $data;
    }

    public function validInvoiceNumbers($filing_id)
    {
        return $this->model->where('filing_id', $filing_id)->where('status', StatusFilingInvoiceEnum::FILINGINVOICE_EST_001)->pluck('invoice_number')->toArray();
    }

    public function getValidationsErrorMessages($id)
    {
        $data = $this->model::find($id);

        // Inicializar un array para almacenar los mensajes de error
        $errorMessages = [];

        $type = 'JSON';
        if ($data->filing->type == TypeFilingEnum::FILING_TYPE_001) {
            $type = 'TXT';
        }
        // Definir las validaciones
        $validations = [
            ['key' => 'validationXml', 'type' => 'XML'],
            ['key' => 'validationTxt', 'type' => $type],
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

    public function paginateThirds($request = [])
    {
        $customTypes = [
            ['value' => 'FILINGINVOICE_EST_001', 'title' => StatusFilingInvoiceEnum::FILINGINVOICE_EST_001->description()],
            ['value' => 'FILINGINVOICE_EST_002', 'title' => StatusFilingInvoiceEnum::FILINGINVOICE_EST_002->description()],
            ['value' => 'FILINGINVOICE_EST_003', 'title' => StatusFilingInvoiceEnum::FILINGINVOICE_EST_003->description()],
            ['value' => 'FILINGINVOICE_EST_004', 'title' => StatusFilingInvoiceEnum::FILINGINVOICE_EST_004->description()],
            ['value' => 'FILINGINVOICE_EST_005', 'title' => StatusFilingInvoiceEnum::FILINGINVOICE_EST_005->description()],
        ];

        $data = request();
        $filter['files_count'] = isset($data['filter']['files_count']) ? $data['filter']['files_count'] : null;

        $this->removeInvalidFilters(['files_count']);

        $cacheKey = $this->cacheService->generateKey("{$this->model->getTable()}_paginate", $request, 'string');

        return $this->cacheService->remember($cacheKey, function () use ($filter, $request, $customTypes) {

            $query = QueryBuilder::for($this->model->query())
                ->withCount(['files'])
                ->allowedFilters([
                    AllowedFilter::callback('inputGeneral', function ($query, $value) {
                        $query->where(function ($query) use ($value) {
                            $query->orWhere('invoice_number', 'like', "%$value%");
                            $query->orWhere('users_count', 'like', "%$value%");
                            $query->orWhere('case_number', 'like', "%$value%");

                            $query->orWhere(function ($subQuery) use ($value) {
                                $normalizedValue = preg_replace('/[\$\s\.,]/', '', $value);
                                $subQuery->orWhere('sumVr', 'like', "%$normalizedValue%");
                            });

                            QueryFilters::filterByText($query, $value, 'status', [
                                StatusFilingInvoiceEnum::FILINGINVOICE_EST_001->description() => StatusFilingInvoiceEnum::FILINGINVOICE_EST_001,
                                StatusFilingInvoiceEnum::FILINGINVOICE_EST_002->description() => StatusFilingInvoiceEnum::FILINGINVOICE_EST_002,
                            ]);
                            QueryFilters::filterByText($query, $value, 'status_xml', [
                                StatusFilingInvoiceEnum::FILINGINVOICE_EST_003->description() => StatusFilingInvoiceEnum::FILINGINVOICE_EST_003,
                                StatusFilingInvoiceEnum::FILINGINVOICE_EST_004->description() => StatusFilingInvoiceEnum::FILINGINVOICE_EST_004,
                            ]);
                        });
                    }),
                    AllowedFilter::custom('status', new DataSelectFilter),
                    AllowedFilter::custom('status_xml', new DataSelectFilter),
                    AllowedFilter::custom('date', new DateRangeFilter),
                ])
                ->allowedSorts([
                    'invoice_number',
                    'users_count',
                    'case_number',
                    'sumVr',
                    'files_count',
                    'date',
                    AllowedSort::custom('status', new StatusOldSort($customTypes)),
                ]);

            if (!empty($request['filing_id'])) {
                $query = $query->where('filing_id', $request['filing_id']);
            }

            if (isset($filter['files_count']) && is_numeric($filter['files_count'])) {
                $query->having('files_count', '=', $filter['files_count']);
            }

            if (!empty($request['user_id'])) {
                $query->whereHas('contract.third', function ($q) use ($request) {
                    $q->whereHas('users', function ($subQuery) use ($request) {
                        $subQuery->where('users.id', $request['user_id']);
                    });
                });
            }

            $query = $query->paginate(request()->perPage ?? Constants::ITEMS_PER_PAGE);

            return $query;
        }, Constants::REDIS_TTL);
    }
}
