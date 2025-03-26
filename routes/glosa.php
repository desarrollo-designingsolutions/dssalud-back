<?php

use App\Http\Controllers\GlosaController;
use Illuminate\Support\Facades\Route;

// Rutas protegidas
Route::middleware(['check.permission:invoiceAuditAssignmentBatche.list'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Glosa
    |--------------------------------------------------------------------------
    */

    Route::get('/glosa/create', [GlosaController::class, 'create']);

    Route::post('/glosa/store', [GlosaController::class, 'store']);

    Route::post('/glosa/uploadCsvGlosa', [GlosaController::class, 'uploadCsvGlosa']);

});
