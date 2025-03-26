<?php

use App\Http\Controllers\AssignmentController;
use Illuminate\Support\Facades\Route;

// Rutas protegidas
Route::middleware(['check.permission:menu.medical.bills'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Assignment
    |--------------------------------------------------------------------------
    */

    Route::get('/assignment/paginateThirds/{assignment_batche_id}', [AssignmentController::class, 'paginateThirds']);

    Route::get('/assignment/paginateInvoiceAudit/{assignment_batche_id}/{third_id}', [AssignmentController::class, 'paginateInvoiceAudit']);

    Route::get('/assignment/paginatePatient/{assignment_batche_id}/{third_id}/{invoice_audit_id}', [AssignmentController::class, 'paginatePatient']);

    Route::post('/assignment/uploadCsv', [AssignmentController::class, 'uploadCsv']);

    Route::post('/assignment/uploadCsvGlosa', [AssignmentController::class, 'uploadCsvGlosa']);

});
