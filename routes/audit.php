<?php

use App\Http\Controllers\AuditController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Audit
|--------------------------------------------------------------------------
*/

Route::get('/audit/timeLine', [AuditController::class, 'timeLine']);

Route::get('/audit/count', [AuditController::class, 'count']);
