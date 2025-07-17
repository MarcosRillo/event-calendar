<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        try {
            $credentials = $request->validate([
                'email' => ['required', 'email'],
                'password' => ['required'],
            ]);

            if (Auth::attempt($credentials)) {
                $request->session()->regenerate();
                return response()->json([
                    'success' => true,
                    'user' => Auth::user(),
                    'message' => 'Login successful',
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during login.',
                'error' => app()->environment('local') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        Log::info('Entrando al método logout', [
            'user_id' => optional($request->user())->id,
            'ip' => $request->ip(),
            'session_id' => $request->session()->getId(),
        ]);
        try {
            $user = $request->user();
            if ($user && method_exists($user, 'currentAccessToken')) {
                $token = $user->currentAccessToken();
                if ($token && get_class($token) !== 'Laravel\\Sanctum\\TransientToken') {
                    $token->delete();
                }
            }
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            Log::info('Logout finalizado correctamente');
            return response()->json(['message' => 'Logout successful'], 200);
        } catch (\Exception $e) {
            Log::error('Error en logout', ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during logout.',
                'error' => app()->environment('local') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
