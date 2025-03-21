<?php

use App\Http\Controllers\InvoiceAuditController;
use Illuminate\Support\Facades\Route;

// Rutas protegidas
Route::middleware(['check.permission:invoiceAudit.list'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | InvoiceAudit
    |--------------------------------------------------------------------------
    */

    Route::get('/invoiceAudit/list', [InvoiceAuditController::class, 'list']);

    Route::get('/invoiceAudit/paginateBatche', [InvoiceAuditController::class, 'paginateBatche']);

    Route::get('/invoiceAudit/paginateThirds/{assignment_batche_id}', [InvoiceAuditController::class, 'paginateThirds']);

    Route::get('/invoiceAudit/paginateInvoiceAudit/{assignment_batche_id}/{third_id}', [InvoiceAuditController::class, 'paginateInvoiceAudit']);

    Route::get('/invoiceAudit/paginatePatient/{assignment_batche_id}/{third_id}/{invoice_audit_id}', [InvoiceAuditController::class, 'paginatePatient']);

});
