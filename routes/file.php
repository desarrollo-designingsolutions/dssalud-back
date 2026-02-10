<?php

use App\Http\Controllers\FileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| File
|--------------------------------------------------------------------------
*/

Route::get('/file/list', [FileController::class, 'list']);

Route::get('/file/create', [FileController::class, 'create']);

Route::post('/file/store', [FileController::class, 'store']);

Route::get('/file/{id}/edit', [FileController::class, 'edit']);

Route::post('/file/update/{id}', [FileController::class, 'update']);

Route::delete('/file/delete/{id}', [FileController::class, 'delete']);

Route::get('/file/download', [FileController::class, 'download']);

Route::post('/file/massUpload', [FileController::class, 'massUpload']);

Route::get('/file/paginate', [FileController::class, 'paginate']);

Route::get('/file/getUrlS3', [FileController::class, 'getUrlS3']);

Route::post('/file/uploadFiles', [FileController::class, 'uploadFiles']);
