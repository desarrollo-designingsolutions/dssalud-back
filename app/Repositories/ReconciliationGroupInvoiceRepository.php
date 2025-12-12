<?php

namespace App\Repositories;

use App\Helpers\Constants;
use App\Models\ReconciliationGroupInvoice;
use App\Models\ConciliationResult;
use App\QueryBuilder\Sort\RelatedTableSort;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class ReconciliationGroupInvoiceRepository extends BaseRepository
{
    public function __construct(ReconciliationGroupInvoice $modelo)
    {
        parent::__construct($modelo);
    }

    public function paginateConciliationInvoices($request = [])
    {
        $cacheKey = $this->cacheService->generateKey("{$this->model->getTable()}_paginateConciliationInvoices", $request, 'string');

        // return $this->cacheService->remember($cacheKey, function () use ($request) {
        $query = QueryBuilder::for($this->model->query())

            ->addSelect([
                'sum_accepted_value_eps' => ConciliationResult::selectRaw('SUM(accepted_value_eps)')
                    ->whereColumn('invoice_audit_id', 'reconciliation_group_invoices.invoice_audit_id'),
                'sum_accepted_value_ips' => ConciliationResult::selectRaw('SUM(accepted_value_ips)')
                    ->whereColumn('invoice_audit_id', 'reconciliation_group_invoices.invoice_audit_id'),
                'sum_eps_ratified_value' => ConciliationResult::selectRaw('SUM(eps_ratified_value)')
                    ->whereColumn('invoice_audit_id', 'reconciliation_group_invoices.invoice_audit_id')
            ])

            ->allowedFilters([

                AllowedFilter::callback('inputGeneral', function ($query, $value) {
                    $query->where(function ($subQuery) use ($value) {

                        $subQuery->orWhereHas('invoiceAudit', function ($invoiceAuditQuery) use ($value) {
                            $invoiceAuditQuery->where(function ($q) use ($value) {
                                $q->where('invoice_number', 'like', "%$value%");
                            });
                        });
                        $subQuery->orWhereHas('invoiceAudit', function ($invoiceAuditQuery) use ($value) {
                            $invoiceAuditQuery->where(function ($q) use ($value) {
                                $q->where('total_value', 'like', "%$value%");
                            });
                        });
                        $subQuery->orWhereHas('invoiceAudit', function ($invoiceAuditQuery) use ($value) {
                            $invoiceAuditQuery->where(function ($q) use ($value) {
                                $q->where('origin', 'like', "%$value%");
                            });
                        });
                        $subQuery->orWhereHas('invoiceAudit', function ($invoiceAuditQuery) use ($value) {
                            $invoiceAuditQuery->where(function ($q) use ($value) {
                                $q->where('modality', 'like', "%$value%");
                            });
                        });
                        $subQuery->orWhereHas('invoiceAudit', function ($invoiceAuditQuery) use ($value) {
                            $invoiceAuditQuery->where(function ($q) use ($value) {
                                $q->where('contract_number', 'like', "%$value%");
                            });
                        });
                    });
                }),
            ])
            ->allowedSorts([
                AllowedSort::custom('invoice_number', new RelatedTableSort(
                    'reconciliation_group_invoices',
                    'invoice_audits',
                    'invoice_number',
                    'invoice_audit_id',
                )),
                AllowedSort::custom('total_value', new RelatedTableSort(
                    'reconciliation_group_invoices',
                    'invoice_audits',
                    'total_value',
                    'invoice_audit_id',
                )),
                AllowedSort::custom('origin', new RelatedTableSort(
                    'reconciliation_group_invoices',
                    'invoice_audits',
                    'origin',
                    'invoice_audit_id',
                )),
                AllowedSort::custom('modality', new RelatedTableSort(
                    'reconciliation_group_invoices',
                    'invoice_audits',
                    'modality',
                    'invoice_audit_id',
                )),
                AllowedSort::custom('contract_number', new RelatedTableSort(
                    'reconciliation_group_invoices',
                    'invoice_audits',
                    'contract_number',
                    'invoice_audit_id',
                )),
                "sum_accepted_value_eps",
                "sum_accepted_value_ips",
                "sum_eps_ratified_value",

            ])
            ->where(function ($query) use ($request) {
                if (! empty($request['company_id'])) {
                    // $query->where('reconciliation_groups.company_id', $request['company_id']);
                }
                if (! empty($request['reconciliation_group_id'])) {
                    $query->where('reconciliation_group_id', $request['reconciliation_group_id']);
                }
            });

        if (empty($request['typeData'])) {
            $query = $query->paginate(request()->perPage ?? Constants::ITEMS_PER_PAGE);
        } else {
            $query = $query->get();
        }

        return $query;
        // }, Constants::REDIS_TTL);
    }

    public function list($request = [], $with = [], $select = ['*'], $idsAllowed = [], $idsNotAllowed = [])
    {
        $cacheKey = $this->cacheService->generateKey("{$this->model->getTable()}_list", $request, 'string');

        return $this->cacheService->remember($cacheKey, function () use ($request, $with) {

            $data = $this->model->with($with)->where(function ($query) {})
                ->where(function ($query) use ($request) {

                    if (! empty($request['company_id'])) {
                        $query->where('company_id', $request['company_id']);
                    }
                })
                ->where(function ($query) use ($request) {
                    if (isset($request['searchQueryInfinite']) && ! empty($request['searchQueryInfinite'])) {
                        $query->orWhere('name', 'like', '%' . $request['searchQueryInfinite'] . '%');
                    }
                });

            $data = $data->orderBy('id', 'desc');
            if (empty($request['typeData'])) {
                $data = $data->paginate($request['perPage'] ?? 10);
            } else {
                $data = $data->get();
            }

            return $data;
        }, Constants::REDIS_TTL);
    }

    public function store(array $request)
    {
        $request = $this->clearNull($request);

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

    public function selectList($request = [], $with = [], $select = [], $fieldValue = 'id', $fieldTitle = 'name')
    {
        $data = $this->model->with($with)->where(function ($query) use ($request) {
            if (! empty($request['idsAllowed'])) {
                $query->whereIn('id', $request['idsAllowed']);
            }
            if (! empty($request['company_id'])) {
                $query->where('company_id', $request['company_id']);
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


    public function getConciliationInvoicesChunk($request = [])
    {
        $query = QueryBuilder::for($this->model->query())
            ->addSelect([
                'sum_accepted_value_eps' => ConciliationResult::selectRaw('SUM(accepted_value_eps)')
                    ->whereColumn('invoice_audit_id', 'reconciliation_group_invoices.invoice_audit_id'),
                'sum_accepted_value_ips' => ConciliationResult::selectRaw('SUM(accepted_value_ips)')
                    ->whereColumn('invoice_audit_id', 'reconciliation_group_invoices.invoice_audit_id'),
                'sum_eps_ratified_value' => ConciliationResult::selectRaw('SUM(eps_ratified_value)')
                    ->whereColumn('invoice_audit_id', 'reconciliation_group_invoices.invoice_audit_id')
            ])
            ->allowedFilters([/* tus filtros actuales */])
            ->allowedSorts([/* tus sorts actuales */])
            ->with(['invoiceAudit.auditoryFinalReport'])
            ->when(!empty($request['reconciliation_group_id']), function ($query) use ($request) {
                $query->where('reconciliation_group_id', $request['reconciliation_group_id']);
            });

        // Aplicar paginación por chunk
        if (isset($request['offset']) && isset($request['limit'])) {
            $query->offset($request['offset'])->limit($request['limit']);
        }

        return $query->get();
    }
}
