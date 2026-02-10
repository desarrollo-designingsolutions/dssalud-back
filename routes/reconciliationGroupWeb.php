<?php

use App\Http\Controllers\ReconciliationGroupWebController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ReconciliationGroup
|--------------------------------------------------------------------------
*/

Route::get('/reconciliationGroup/index/{id}', [ReconciliationGroupWebController::class, 'index']);

Route::post('/reconciliationGroup/saveNotification', [ReconciliationGroupWebController::class, 'saveNotification'])->name('reconciliationGroup.saveNotification');
