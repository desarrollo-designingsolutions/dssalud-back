<?php

use App\Http\Controllers\AssignmentBatcheController;
use Illuminate\Support\Facades\Route;

// Rutas protegidas
Route::middleware(['check.permission:assignmentBatche.list'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | AssignmentBatche
    |--------------------------------------------------------------------------
    */

    Route::get('/assignmentBatche/paginate', [AssignmentBatcheController::class, 'paginate']);

    Route::get('/assignmentBatche/create', [AssignmentBatcheController::class, 'create']);

    Route::post('/assignmentBatche/store', [AssignmentBatcheController::class, 'store']);

    Route::get('/assignmentBatche/{id}/edit', [AssignmentBatcheController::class, 'edit']);

    Route::post('/assignmentBatche/update/{id}', [AssignmentBatcheController::class, 'update']);

    Route::delete('/assignmentBatche/delete/{id}', [AssignmentBatcheController::class, 'delete']);

});
