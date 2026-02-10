<?php

use App\Http\Controllers\InvoiceAuditController;
use Illuminate\Support\Facades\Route;

// Rutas protegidas
Route::middleware(['check.permission:invoiceAuditAssignmentBatche.list'])->group(function () {

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

    Route::get('/invoiceAudit/getInformationSheet/{third_id}/{invoice_audit_id}/{patient_id}', [InvoiceAuditController::class, 'getInformationSheet']);

    Route::get('/invoiceAudit/getServices/{invoice_audit_id}/{patient_id}', [InvoiceAuditController::class, 'getServices']);

    Route::post('/invoiceAudit/exportListServicesExcel', [InvoiceAuditController::class, 'exportListServicesExcel']);

    Route::post('/invoiceAudit/exportDataToGlosasImportCsv', [InvoiceAuditController::class, 'exportDataToGlosasImportCsv']);

    Route::get('/invoiceAudit/excelErrorsValidation', [InvoiceAuditController::class, 'excelErrorsValidation']);

    Route::get('/invoiceAudit/exportCsvErrorsValidation', [InvoiceAuditController::class, 'exportCsvErrorsValidation']);

    Route::post('/invoiceAudit/successFinalizedAudit', [InvoiceAuditController::class, 'successFinalizedAudit']);

    Route::post('/invoiceAudit/successReturnAudit', [InvoiceAuditController::class, 'successReturnAudit']);
});
