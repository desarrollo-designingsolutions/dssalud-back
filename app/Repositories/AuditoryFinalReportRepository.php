<?php

namespace App\Repositories;

use App\Helpers\Constants;
use App\Models\AuditoryFinalReport;
use Spatie\QueryBuilder\QueryBuilder;

class AuditoryFinalReportRepository extends BaseRepository
{
    public function __construct(AuditoryFinalReport $modelo)
    {
        parent::__construct($modelo);
    }

    public function paginate($request = [])
    {
        $cacheKey = $this->cacheService->generateKey("{$this->model->getTable()}_paginate", $request, 'string');

        return $this->cacheService->remember($cacheKey, function () use ($request) {
            $query = QueryBuilder::for($this->model->query())
                ->allowedFilters([])
                ->allowedSorts([])
                ->where(function ($query) use ($request) {
                    if (! empty($request['company_id'])) {
                        $query->where('company_id', $request['company_id']);
                    }
                });
            $query = $query->paginate(request()->perPage ?? Constants::ITEMS_PER_PAGE);

            return $query;
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


    public function getInvoicesChunk($invoices_ids = [])
    {
        return AuditoryFinalReport::whereIn("factura_id", $invoices_ids)->get()
            ->map(function ($value) {
                return [
                    "iddd" => $value->invoiceAudit?->id,
                    "invoice_number" => $value->invoiceAudit?->invoice_number,
                    "sub_invoice_number" => $value->invoiceAudit?->invoice_number,
                    "gloss_code" =>  $value->codigos_glosa,
                    "contract_number" => $value->contrato,
                    "total_value" => formatNumber($value->invoiceAudit?->total_value),
                    "invoiced_month" => $value->invoiceAudit?->date_entry,
                    "affiliated_department" => $value->invoiceAudit?->third?->departmentAndCity?->departamento,
                    "initial_gloss_value" => formatNumber($value->valor_glosa),
                    "pending_value" => "0",
                    "accepted_value_eps" => formatNumber($value->conciliationResult?->accepted_value_eps),
                    "accepted_value_ips" => formatNumber($value->conciliationResult?->accepted_value_ips),
                    "ratified_value" => formatNumber($value->conciliationResult?->eps_ratified_value),
                    "justification" => "viene de la observacion de la tabla conciliation result",

                ];
            });
    }
}
