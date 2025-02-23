<?php

use App\Http\Controllers\FilingController;
use Illuminate\Support\Facades\Route;

//Rutas protegidas
Route::middleware(['check.permission:filing.new.index'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Filing OLD
    |--------------------------------------------------------------------------
    */

    Route::post('/filing/uploadZip', [FilingController::class, 'uploadZip']);

    Route::post('/filing/showErrorsValidation', [FilingController::class, 'showErrorsValidation']);

    Route::post('/filing/excelErrorsValidation', [FilingController::class, 'excelErrorsValidation']);

    Route::delete('/filing/delete/{id}', [FilingController::class, 'delete']);

    Route::post('/filing/updateContract', [FilingController::class, 'updateContract']);


    /*
    |--------------------------------------------------------------------------
    | Filing Invoices
    |--------------------------------------------------------------------------
    */

    Route::get('/filing/list', [FilingController::class, 'list']);

    Route::get('/filing/countAllDataFiling', [FilingController::class, 'countAllDataFiling']);

    Route::get('/filing-invoices/{invoiceId}/users', [FilingController::class, 'getPaginatedUsers']);
});
