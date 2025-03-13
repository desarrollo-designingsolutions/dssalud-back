<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle($request, Closure $next, $permission): Response
    {
        // Verifica si el usuario está autenticado
        if (! Auth::check()) {
            return response()->json([
                'code' => 401,
                'message' => 'Usuario no autenticado',
            ], 401);
        }

        if (Auth::check() && auth()->user()->is_active != 1) {
            return response()->json(['a' => auth()->user()->is_active, 'code' => 404, 'message' => 'Usted ha sido inactivado'], 404);
        }

        $user = Auth::user();

        // Verifica si el usuario tiene el permiso requerido
        if (! $user->hasPermissionTo($permission)) {
            return response()->json([
                'code' => 500,
                'message' => 'Usted no está autorizado para acceder a este recurso',
            ], 500);
        }

        return $next($request);
    }
}
