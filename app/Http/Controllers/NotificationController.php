<?php

namespace App\Http\Controllers;

use App\Events\UpdateNotificationEvent;
use App\Http\Requests\Notification\NotificationStoreRequest;
use App\Http\Resources\Notification\NotificationListResource;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Throwable;

class NotificationController extends Controller
{
    public function __construct(
        protected UserRepository $userRepository,
    ) {}

    public function getNotifications($userId, Request $request)
    {
        $user = $this->userRepository->find($userId);

        $perPage = 3;

        // Obtener el conteo de las notificaciones no leídas
        $unreadCount = $user->notificaciones()
            ->where('is_removed', false)
            ->whereNull('read_at')
            ->count();

        $notifications = $user->notificaciones()->where(function ($query) {
            $query->where('is_removed', false);
        })->orderBy('read_at', 'asc')
            ->orderBy('created_at', 'asc')
            ->paginate($perPage);

        return response()->json([
            'notifications' => NotificationListResource::collection($notifications),
            'nextPageUrl' => $notifications->nextPageUrl(),  // URL de la siguiente página
            'unreadCount' => $unreadCount,  // URL de la siguiente página
        ]);
    }

    public function markMultipleAsRead(NotificationStoreRequest $request)
    {
        try {

            $user = auth()->user(); // Obtener el usuario autenticado

            // Marcar las notificaciones como leídas
            $user->notifications->whereIn('id', $request->input('notification_ids'))->markAsRead();

            UpdateNotificationEvent::dispatch($user, $request->input('notification_ids'));

            return response()->json(['code' => 200]);
        } catch (Throwable $th) {
            return response()->json([
                'code' => 500,
                'message' => 'Algo Ocurrio, Comunicate Con El Equipo De Desarrollo',
                $th->getMessage(),
                $th->getLine(),
            ]);
        }
    }

    public function markMultipleAsUnread(NotificationStoreRequest $request)
    {
        try {

            $user = auth()->user(); // Obtener el usuario autenticado

            // Marcar las notificaciones como no leídas
            $user->notifications->whereIn('id', $request->input('notification_ids'))->markAsUnread();

            UpdateNotificationEvent::dispatch($user, $request->input('notification_ids'));

            return response()->json(['code' => 200]);
        } catch (Throwable $th) {
            return response()->json([
                'code' => 500,
                'message' => 'Algo Ocurrio, Comunicate Con El Equipo De Desarrollo',
                $th->getMessage(),
                $th->getLine(),
            ]);
        }
    }

    public function removeNotification(Request $request)
    {
        try {
            $user = auth()->user(); // Obtener el usuario autenticado

            $notificationIds = $request->input('notification_ids'); // Array de IDs de notificaciones

            // Obtener las notificaciones del usuario y actualizarlas
            $user->notifications()
                ->whereIn('id', $notificationIds)  // Filtrar por los IDs proporcionados
                ->update(['is_removed' => true]);  // Actualizar el campo is_removed a true

            UpdateNotificationEvent::dispatch($user, $notificationIds);

            return response()->json(['code' => 200]);
        } catch (Throwable $th) {
            return response()->json([
                'code' => 500,
                'message' => 'Algo Ocurrio, Comunicate Con El Equipo De Desarrollo',
                $th->getMessage(),
                $th->getLine(),
            ]);
        }
    }
}
