<?php

use App\Http\Controllers\ScheduleConciliationController;
use App\Http\Controllers\ScheduleController;
use Illuminate\Support\Facades\Route;

// Rutas protegidas
Route::middleware(['check.permission:schedule.menu'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Schedule
    |--------------------------------------------------------------------------
    */

    Route::get('/schedule/index', [ScheduleController::class, 'index']);

    Route::get('/schedule/dataView', [ScheduleController::class, 'dataView']);

    /*
    |--------------------------------------------------------------------------
    | Schedule Type Event Conciliation
    |--------------------------------------------------------------------------
    */

    Route::get('/schedule/conciliation/create', [ScheduleConciliationController::class, 'create']);

    Route::post('/schedule/conciliation/store', [ScheduleConciliationController::class, 'store']);

    Route::get('/schedule/conciliation/edit/{id}', [ScheduleConciliationController::class, 'edit']);

    Route::post('/schedule/conciliation/update/{id}', [ScheduleConciliationController::class, 'update']);

    Route::get('/schedule/conciliation/show/{event_id}', [ScheduleConciliationController::class, 'show']);

    Route::delete('/schedule/conciliation/delete/{id}', [ScheduleConciliationController::class, 'delete']);

    Route::get('/schedule/conciliation/getAcceptDataEvent/{id}', [ScheduleConciliationController::class, 'getAcceptDataEvent']);

    Route::get('/schedule/conciliation/acceptInvitation/{id}', [ScheduleConciliationController::class, 'acceptInvitation']);

    Route::get('/schedule/conciliation/rejectInvitation/{id}', [ScheduleConciliationController::class, 'rejectInvitation']);

    Route::get('/schedule/conciliation/paginateAgenda', [ScheduleConciliationController::class, 'paginateAgenda']);

    Route::get('/schedule/conciliation/excelExport', [ScheduleConciliationController::class, 'excelExport']);
});
