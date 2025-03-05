<?php

use App\Http\Controllers\InvoiceAuditController;
use Illuminate\Support\Facades\Route;

//Rutas protegidas
Route::middleware(["check.permission:invoiceAudit.list"])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | InvoiceAudit
    |--------------------------------------------------------------------------
    */

    Route::get('/invoiceAudit/list', [InvoiceAuditController::class, 'list']);

});
