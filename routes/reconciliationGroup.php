<?php

use App\Http\Controllers\ReconciliationGroupController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ReconciliationGroup
|--------------------------------------------------------------------------
*/

Route::get('/reconciliationGroup/index/{id}', [ReconciliationGroupController::class, 'index']);
