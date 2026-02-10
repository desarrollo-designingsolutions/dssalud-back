<?php

use App\Http\Controllers\CompanyController;
use Illuminate\Support\Facades\Route;

// Rutas protegidas
Route::middleware(['check.permission:company.list'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Company
    |--------------------------------------------------------------------------
    */

    Route::get('/company/paginate', [CompanyController::class, 'paginate']);

    Route::get('/company/create', [CompanyController::class, 'create']);

    Route::post('/company/store', [CompanyController::class, 'store']);

    Route::get('/company/{id}/edit', [CompanyController::class, 'edit']);

    Route::post('/company/update/{id}', [CompanyController::class, 'update']);

    Route::delete('/company/delete/{id}', [CompanyController::class, 'delete']);

    Route::post('/company/changeStatus', [CompanyController::class, 'changeStatus']);
});
