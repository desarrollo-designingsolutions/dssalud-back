<?php

use App\Http\Controllers\ConciliationController;
use Illuminate\Support\Facades\Route;


// Rutas protegidas
// Route::middleware(['check.permission:conciliation.index'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Conciliation
    |--------------------------------------------------------------------------
    */
    Route::post('/conciliation/uploadFile', [ConciliationController::class, 'uploadFile']);

// });
