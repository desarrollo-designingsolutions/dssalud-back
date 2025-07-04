<?php

use App\Http\Controllers\ScheduleController;
use Illuminate\Support\Facades\Route;

//Rutas protegidas
Route::middleware(['check.permission:schedule.menu'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Schedule
    |--------------------------------------------------------------------------
    */

    Route::get('/schedule/index', [ScheduleController::class, 'index']);

    Route::get('/schedule/getDataEvent/{event_id}', [ScheduleController::class, 'getDataEvent']);

    Route::get('/schedule/dataView', [ScheduleController::class, 'dataView']);

    Route::get('/schedule/dataViewForm', [ScheduleController::class, 'dataViewForm']);

    Route::get('/schedule/dataEditForm/{id}', [ScheduleController::class, 'dataEditForm']);
    
    Route::post('/schedule/store', [ScheduleController::class, 'store']);
    
    Route::post('/schedule/update/{id}', [ScheduleController::class, 'update']);
    
    Route::delete('/schedule/delete/{id}', [ScheduleController::class, 'delete']);

    Route::get('/schedule/getAcceptDataEvent/{id}', [ScheduleController::class, 'getAcceptDataEvent']);

    Route::get('/schedule/acceptInvitation/{id}', [ScheduleController::class, 'acceptInvitation']);

    Route::get('/schedule/rejectInvitation/{id}', [ScheduleController::class, 'rejectInvitation']);
});
