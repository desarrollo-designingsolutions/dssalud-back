<?php

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Rutas protegidas
Route::middleware(['check.permission:menu.user'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | User
    |--------------------------------------------------------------------------
    */

    Route::get('/user/paginate', [UserController::class, 'paginate']);

    Route::get('/user/create', [UserController::class, 'create']);

    Route::post('/user/store', [UserController::class, 'store']);

    Route::get('/user/{id}/edit', [UserController::class, 'edit']);

    Route::post('/user/update/{id}', [UserController::class, 'update']);

    Route::delete('/user/delete/{id}', [UserController::class, 'delete']);

    Route::post('/user/changeStatus', [UserController::class, 'changeStatus']);

});

Route::post('/user/changePassword', [UserController::class, 'changePassword']);

Route::post('/user/changePhoto', [UserController::class, 'changePhoto']);
