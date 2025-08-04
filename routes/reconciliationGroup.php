<?php

use App\Http\Controllers\ReconciliationGroupController;
use Illuminate\Support\Facades\Route;

// Rutas protegidas
Route::middleware(['check.permission:reconciliationGroup.list'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | ReconciliationGroup
    |--------------------------------------------------------------------------
    */
    Route::get('/reconciliationGroup/paginate', [ReconciliationGroupController::class, 'paginate']);

    Route::get('/reconciliationGroup/list', [ReconciliationGroupController::class, 'list']);

    Route::get('/reconciliationGroup/create', [ReconciliationGroupController::class, 'create']);

    Route::post('/reconciliationGroup/store', [ReconciliationGroupController::class, 'store']);

    Route::get('/reconciliationGroup/{id}/edit', [ReconciliationGroupController::class, 'edit']);

    Route::post('/reconciliationGroup/update/{id}', [ReconciliationGroupController::class, 'update']);

    Route::delete('/reconciliationGroup/delete/{id}', [ReconciliationGroupController::class, 'delete']);

    Route::post('/reconciliationGroup/changeStatus', [ReconciliationGroupController::class, 'changeStatus']);

    Route::get('/reconciliationGroup/excelExport', [ReconciliationGroupController::class, 'excelExport']);

});
