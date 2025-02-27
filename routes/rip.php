<?php

use App\Http\Controllers\RipAutomaticController;
// use App\Http\Controllers\RipManualController;
use Illuminate\Support\Facades\Route;

//Rutas protegidas
Route::middleware(['check.permission:rips.automatic.list'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Rips Automotic
    |--------------------------------------------------------------------------
    */

    Route::post('/rip/uploadZip', [RipAutomaticController::class, 'uploadZip']);

    Route::get('/rip/list', [RipAutomaticController::class, 'list']);

    Route::post('/rip/storeJson', [RipAutomaticController::class, 'storeJson']);

    Route::post('/rip/showErrorsValidation', [RipAutomaticController::class, 'showErrorsValidation']);

    Route::post('/rip/excelErrorsValidation', [RipAutomaticController::class, 'excelErrorsValidation']);

    // Route::post('/rip-uploadExcel', [RipAutomaticController::class, 'uploadExcel']);

    // // Route::post('/rip-storeExcel', [RipAutomaticController::class, 'storeExcel']);

    // Route::post('/rip-uploadXlm', [RipAutomaticController::class, 'uploadXlm']);

    // Route::get('/rip-info/{id}', [RipAutomaticController::class, 'info']);

    // Route::delete('/rip-delete/{id}', [RipAutomaticController::class, 'delete']);

    // Route::get('/validation-txt/{id}', [RipAutomaticController::class, 'validation_txt']);
    /*
    |--------------------------------------------------------------------------
    | Rips Manual
    |--------------------------------------------------------------------------
    */

    // Route::get('/ripManual-dataFormModalCreate', [RipManualController::class, 'dataFormModalCreate']);

    // Route::post('/ripManual-list', [RipManualController::class, 'list']);

    // Route::post('/ripManual-storeRips', [RipManualController::class, 'storeRips']);

    // Route::get('/ripManual-invoiceViewData/{id}', [RipManualController::class, 'invoiceViewData']);

    // Route::post('/ripManual-storeInvoice', [RipManualController::class, 'storeInvoice']);

    // Route::get('/ripManual-userViewData/{id}', [RipManualController::class, 'userViewData']);

    // Route::post('/ripManual-storeUsers', [RipManualController::class, 'storeUsers']);

    // Route::get('/ripManual-serviceViewData/{id}', [RipManualController::class, 'serviceViewData']);

    // Route::post('/ripManual-storeServices', [RipManualController::class, 'storeServices']);

});
