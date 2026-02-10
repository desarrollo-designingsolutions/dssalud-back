<?php

use App\Http\Controllers\ConciliationController;
use Illuminate\Support\Facades\Route;

// Rutas protegidas
Route::middleware(['check.permission:conciliation.list'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Conciliation
    |--------------------------------------------------------------------------
    */

    Route::get('/conciliation/paginateConciliation', [ConciliationController::class, 'paginateConciliation']);

    Route::get('/conciliation/{id}/show', [ConciliationController::class, 'show']);

    Route::get('/conciliation/excelExportConciliation', [ConciliationController::class, 'excelExportConciliation']);

    Route::get('/conciliation/paginateConciliationInvoices', [ConciliationController::class, 'paginateConciliationInvoices']);

    Route::get('/conciliation/excelExportConciliationInvoices', [ConciliationController::class, 'excelExportConciliationInvoices']);

    Route::post('/conciliation/uploadFile', [ConciliationController::class, 'uploadFile']);


    /*
    |--------------------------------------------------------------------------
    | Conciliation Change Status
    |--------------------------------------------------------------------------
    */


    Route::get('/conciliation/changeStatus/form', [ConciliationController::class, 'changeStatusForm']);

    Route::post('/conciliation/changeStatus/save', [ConciliationController::class, 'changeStatusSave']);

    /*
    |--------------------------------------------------------------------------
    | Conciliation Generate Conciliation Report
    |--------------------------------------------------------------------------
    */

    Route::get('/conciliation/generateConciliationReport/form', [ConciliationController::class, 'generateConciliationReportForm']);

    Route::post('/conciliation/generateConciliationReport/save', [ConciliationController::class, 'generateConciliationReportSave']);
});
