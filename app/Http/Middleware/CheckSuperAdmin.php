<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CheckSuperAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Log del intento de acceso para auditoría
        Log::info('SuperAdmin access attempt', [
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'route' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toISOString(),
        ]);

        // Verificar que el usuario esté autenticado
        if (!$user) {
            Log::warning('SuperAdmin access denied - No authentication', [
                'route' => $request->path(),
                'ip' => $request->ip(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Authentication required.',
            ], 401);
        }

        // Verificar que el usuario sea una instancia válida del modelo User
        if (!$user instanceof \App\Models\User) {
            Log::warning('SuperAdmin access denied - Invalid user object', [
                'route' => $request->path(),
                'ip' => $request->ip(),
                'user_type' => get_class($user),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Authentication required.',
            ], 401);
        }

        // Verificar que el usuario tenga el rol de superadmin
        if (!$user->isSuperAdmin()) {
            Log::warning('SuperAdmin access denied - Insufficient privileges', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'route' => $request->path(),
                'ip' => $request->ip(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Super admin access required.',
            ], 403);
        }

        // Log de acceso exitoso
        Log::info('SuperAdmin access granted', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'route' => $request->path(),
            'method' => $request->method(),
        ]);

        return $next($request);
    }
}
