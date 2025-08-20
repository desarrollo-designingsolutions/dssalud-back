<?php

use App\Http\Controllers\QueryController;
use Illuminate\Support\Facades\Route;

// Lista de Pais, Departamentos y Ciudades
Route::post('/selectInfiniteCountries', [QueryController::class, 'selectInfiniteCountries']);
Route::get('/selectStates/{country_id}', [QueryController::class, 'selectStates']);
Route::get('/selectCities/{state_id}', [QueryController::class, 'selectCities']);
Route::get('/selectCities/country/{country_id}', [QueryController::class, 'selectCitiesCountry']);
// Lista de Pais, Departamentos y Ciudades

Route::post('/selectStatusFilingInvoiceEnum', [QueryController::class, 'selectStatusFilingInvoiceEnum']);
Route::post('/selectStatusFilingInvoiceTypeEnum', [QueryController::class, 'selectStatusFilingInvoiceTypeEnum']);
Route::post('/selectStatusXmlFilingInvoiceEnum', [QueryController::class, 'selectStatusXmlFilingInvoiceEnum']);

Route::post('/selectInfiniteContract', [QueryController::class, 'selectInfiniteContract']);
Route::post('/selectInfiniteSupportType', [QueryController::class, 'selectInfiniteSupportType']);

Route::post('/selectStatusFilingEnumOpenAndClosed', [QueryController::class, 'selectStatusFilingEnumOpenAndClosed']);

Route::post('/selectRoleTypeEnum', [QueryController::class, 'selectRoleTypeEnum']);

Route::post('/selectTypeEventEnum', [QueryController::class, 'selectTypeEventEnum']);

Route::post('/selectScheduleResponseStatusEnum', [QueryController::class, 'selectScheduleResponseStatusEnum']);

Route::post('/selectInfiniteThird', [QueryController::class, 'selectInfiniteThird']);

Route::post('/selectInfiniteCodeGlosa', [QueryController::class, 'selectInfiniteCodeGlosa']);

Route::post('/selectInfiniteUser', [QueryController::class, 'selectInfiniteUser']);

Route::post('/selectInfiniteReconciliationGroup', [QueryController::class, 'selectInfiniteReconciliationGroup']);

Route::post('/selectStatusReconciliationGroupEnum', [QueryController::class, 'selectStatusReconciliationGroupEnum']);

