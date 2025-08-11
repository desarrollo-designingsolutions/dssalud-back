<?php

use App\Http\Controllers\ProcessLogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ProcessBatch
|--------------------------------------------------------------------------
*/

Route::get('/processBatch/errorsPaginate', [ProcessLogController::class, 'paginate']);

Route::get('/processBatch/getUserProcesses/{id}', [ProcessLogController::class, 'getUserProcesses']);
