<?php

use App\Http\Controllers\AssignmentController;
use Illuminate\Support\Facades\Route;

// Rutas protegidas
Route::middleware(['check.permission:menu.medical.bills'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Assignment
    |--------------------------------------------------------------------------
    */

    Route::get('/assignment/paginate/{id}', [AssignmentController::class, 'paginate']);

    Route::delete('/assignment/delete/{id}', [AssignmentController::class, 'delete']);

    Route::post('/assignment/uploadCsv', [AssignmentController::class, 'uploadCsv']);

});
