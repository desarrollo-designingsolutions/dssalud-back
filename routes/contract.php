<?php

use App\Http\Controllers\ContractController;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| Contract
|--------------------------------------------------------------------------
*/

Route::get('/contract/paginate', [ContractController::class, 'paginate']);

Route::get('/contract/list', [ContractController::class, 'list']);

Route::get('/contract/create', [ContractController::class, 'create']);

Route::post('/contract/store', [ContractController::class, 'store']);

Route::get('/contract/{id}/edit', [ContractController::class, 'edit']);

Route::post('/contract/update/{id}', [ContractController::class, 'update']);

Route::delete('/contract/delete/{id}', [ContractController::class, 'delete']);

