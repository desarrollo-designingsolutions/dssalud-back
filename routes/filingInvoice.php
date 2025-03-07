<?php

use App\Http\Controllers\FilingInvoiceController;
use Illuminate\Support\Facades\Route;

//Rutas protegidas
Route::middleware(['check.permission:filing.new.index'])->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Filing Invoices
    |--------------------------------------------------------------------------
    */

    Route::get('/filingInvoice/list', [FilingInvoiceController::class, 'list']);

    Route::get('/filingInvoice/countAllDataFiling', [FilingInvoiceController::class, 'countAllDataFiling']);

    Route::get('/filingInvoice/{invoiceId}/users', [FilingInvoiceController::class, 'getPaginatedUsers']);

    Route::post('/filingInvoice/uploadXml', [FilingInvoiceController::class, 'uploadXml']);

    Route::get('/filingInvoice/showErrorsValidation/{filingInvoicesId}', [FilingInvoiceController::class, 'showErrorsValidation']);

    Route::post('/filingInvoice/excelErrorsValidation', [FilingInvoiceController::class, 'excelErrorsValidation']);

    Route::delete('/filingInvoice/delete/{invoiceId}', [FilingInvoiceController::class, 'delete']);
});
