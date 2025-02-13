<?php

use App\Http\Controllers\QueryController;
use Illuminate\Support\Facades\Route;

// Lista de Pais, Departamentos y Ciudades
Route::post('/selectInfiniteCountries', [QueryController::class, 'selectInfiniteCountries']);
Route::get('/selectStates/{country_id}', [QueryController::class, 'selectStates']);
Route::get('/selectCities/{state_id}', [QueryController::class, 'selectCities']);
Route::get('/selectCities/country/{country_id}', [QueryController::class, 'selectCitiesCountry']);
// Lista de Pais, Departamentos y Ciudades

// Route::post('/selectInifiniteInsurance', [QueryController::class, 'selectInifiniteInsurance']);

