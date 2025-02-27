<?php

use App\Http\Controllers\QueryController;
use Illuminate\Support\Facades\Route;

// Lista de Pais, Departamentos y Ciudades
Route::post('/selectInfiniteCountries', [QueryController::class, 'selectInfiniteCountries']);
Route::get('/selectStates/{country_id}', [QueryController::class, 'selectStates']);
Route::get('/selectCities/{state_id}', [QueryController::class, 'selectCities']);
Route::get('/selectCities/country/{country_id}', [QueryController::class, 'selectCitiesCountry']);
// Lista de Pais, Departamentos y Ciudades

Route::post('/selectStatusFillingInvoiceEnum', [QueryController::class, 'selectStatusFillingInvoiceEnum']);
Route::post('/selectStatusXmlFillingInvoiceEnum', [QueryController::class, 'selectStatusXmlFillingInvoiceEnum']);

Route::post('/selectInfiniteContract', [QueryController::class, 'selectInfiniteContract']);
Route::post('/selectInfiniteSupportType', [QueryController::class, 'selectInfiniteSupportType']);
