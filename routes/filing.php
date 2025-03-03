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

    Route::get('/filing/list', [FilingController::class, 'list']);

    Route::get('/filing/showData/{id}', [FilingController::class, 'showData']);

    Route::post('/filing/uploadZip', [FilingController::class, 'uploadZip']);

    Route::post('/filing/showErrorsValidation', [FilingController::class, 'showErrorsValidation']);

    Route::post('/filing/excelErrorsValidation', [FilingController::class, 'excelErrorsValidation']);

    Route::delete('/filing/delete/{id}', [FilingController::class, 'delete']);

    Route::get('/filing/updateValidationTxt/{id}', [FilingController::class, 'updateValidationTxt']);

    Route::post('/filing/updateContract', [FilingController::class, 'updateContract']);

    Route::get('/filing/{id}/getDataModalSupportMasiveFiles', [FilingController::class, 'getDataModalSupportMasiveFiles']);

    Route::post('/filing/saveDataModalSupportMasiveFiles', [FilingController::class, 'saveDataModalSupportMasiveFiles']);

    Route::post('/filing/uploadJson', [FilingController::class, 'uploadJson']);

    Route::get('/filing/{id}/getDataModalXmlMasiveFiles', [FilingController::class, 'getDataModalXmlMasiveFiles']);

    Route::post('/filing/saveDataModalXmlMasiveFiles', [FilingController::class, 'saveDataModalXmlMasiveFiles']);

    Route::get('/filing/getAllValidation/{id}', [FilingController::class, 'getAllValidation']);

    Route::post('/filing/excelAllValidation', [FilingController::class, 'excelAllValidation']);

    Route::get('/filing/getCountFilingInvoicePreRadicated/{id}', [FilingController::class, 'getCountFilingInvoicePreRadicated']);

    Route::get('/filing/changeStatusFilingInvoicePreRadicated/{id}', [FilingController::class, 'changeStatusFilingInvoicePreRadicated']);

});
