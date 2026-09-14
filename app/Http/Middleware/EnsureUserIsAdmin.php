<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    # PERMITE EL PASO SOLO SI EL USUARIO AUTENTICADO TIENE EL ROL admin
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('api')->user();

        if (! $user || $user->role?->role_name !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'No tenés permisos de administrador para acceder a este recurso.',
            ], 403);
        }

        return $next($request);
    }
}
