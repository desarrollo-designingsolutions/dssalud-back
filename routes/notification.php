<?php

use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Notifications
|--------------------------------------------------------------------------
*/

// Obtener als notificaciones del usuario
Route::get('/notification/getNotifications/{userId}', [NotificationController::class, 'getNotifications']);

// Marcar múltiples notificaciones como leídas
Route::post('notification/markMultipleAsRead', [NotificationController::class, 'markMultipleAsRead']);

// Marcar múltiples notificaciones como no leídas
Route::post('notification/markMultipleAsUnread', [NotificationController::class, 'markMultipleAsUnread']);

// Define la ruta para eliminar notificaciones
Route::post('/notifications/remove', [NotificationController::class, 'removeNotification']);
