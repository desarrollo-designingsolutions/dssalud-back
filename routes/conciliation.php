<?php

use App\Http\Controllers\ConciliationController;
use App\Http\Controllers\ProcessLogController;
use Illuminate\Support\Facades\Route;


// Rutas protegidas
// Route::middleware(['check.permission:conciliation.index'])->group(function () {

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


// });
