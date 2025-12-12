<?php

use App\Http\Controllers\FileViewerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| FileViewer
|--------------------------------------------------------------------------
*/

Route::post('/file/listfolders', [FileViewerController::class, 'listfolders']);
