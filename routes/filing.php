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

    Route::get('/filing/showData/{id}', [FilingController::class, 'showData']);

    Route::post('/filing/uploadZip', [FilingController::class, 'uploadZip']);

    Route::post('/filing/showErrorsValidation', [FilingController::class, 'showErrorsValidation']);

    Route::post('/filing/excelErrorsValidation', [FilingController::class, 'excelErrorsValidation']);

    Route::delete('/filing/delete/{id}', [FilingController::class, 'delete']);

    Route::post('/filing/updateContract', [FilingController::class, 'updateContract']);

    Route::get('/filing/{id}/getDataModalSupportMasiveFiles', [FilingController::class, 'getDataModalSupportMasiveFiles']);

    Route::post('/filing/saveDataModalSupportMasiveFiles', [FilingController::class, 'saveDataModalSupportMasiveFiles']);

    Route::post('/filing/uploadJson', [FilingController::class, 'uploadJson']);

    Route::get('/filing/{id}/getDataModalXmlMasiveFiles', [FilingController::class, 'getDataModalXmlMasiveFiles']);

    Route::post('/filing/saveDataModalXmlMasiveFiles', [FilingController::class, 'saveDataModalXmlMasiveFiles']);

    Route::post('/filing/getValidationTxtByFilingId/{id}', [FilingController::class, 'getValidationTxtByFilingId']);

});
