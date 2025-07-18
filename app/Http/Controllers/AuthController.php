<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class AuthController extends Controller
{
    /**
     * Get the authenticated user with proper typing and relationships loaded
     */
    private function getAuthenticatedUser(): User
    {
        /** @var User $user */
        $user = User::with('role')->find(Auth::id());
        return $user;
    }
    public function login(Request $request)
    {
        try {
            $credentials = $request->validate([
                'email' => ['required', 'email'],
                'password' => ['required'],
            ]);

            if (Auth::attempt($credentials)) {
                $request->session()->regenerate();
                
                $user = $this->getAuthenticatedUser();
                
                return response()->json([
                    'success' => true,
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role ? $user->role->name : null,
                        'is_super_admin' => $user->isSuperAdmin(),
                        'is_organization_admin' => $user->isOrganizationAdmin(),
                    ],
                    'message' => 'Login successful',
                ], 200);
            }

            // Log de credenciales inválidas
            Log::warning('Login failed - Invalid credentials', [
                'email' => $credentials['email'],
                'ip' => $request->ip(),
                'timestamp' => now()->toISOString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        } catch (\Exception $e) {
            // Log de error durante login
            Log::error('Error during login', [
                'error' => $e->getMessage(),
                'email' => $request->input('email'),
                'ip' => $request->ip(),
                'timestamp' => now()->toISOString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during login.',
                'error' => app()->environment('local') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function logout(Request $request)
    {
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
            
            return response()->json([
                'success' => true,
                'message' => 'Logout successful'
            ], 200);
        } catch (\Exception $e) {
            // Log de error durante logout
            Log::error('Error during logout', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
                'timestamp' => now()->toISOString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during logout.',
                'error' => app()->environment('local') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function profile()
    {
        $user = $this->getAuthenticatedUser();
        
        return response()->json([
            'user' => $user,
            'is_super_admin' => $user->isSuperAdmin(),
            'is_organization_admin' => $user->isOrganizationAdmin(),
        ]);
    }
}
