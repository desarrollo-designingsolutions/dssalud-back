<?php

namespace App\Http\Controllers;

use App\Http\Resources\InvoiceAudit\InvoiceAuditListResource;
use App\Repositories\InvoiceAuditRepository;
use App\Traits\HttpTrait;
use Illuminate\Http\Request;

class InvoiceAuditController extends Controller
{
    use HttpTrait;

    public function __construct(
        protected InvoiceAuditRepository $invoiceAuditRepository,
    ) {}

    public function list(Request $request)
    {
        return $this->execute(function () use ($request) {

            $invoiceAudit = $this->invoiceAuditRepository->list($request->all());
            $tableData = InvoiceAuditListResource::collection($invoiceAudit);

            return [
                'code' => 200,
                'tableData' => $tableData,
                'lastPage' => $invoiceAudit->lastPage(),
                'totalData' => $invoiceAudit->total(),
                'totalPage' => $invoiceAudit->perPage(),
                'currentPage' => $invoiceAudit->currentPage(),
            ];
        });
    }

}
