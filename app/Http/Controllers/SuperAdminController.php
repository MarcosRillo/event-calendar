<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Organization;
use App\Models\Event;
use App\Models\Role;

class SuperAdminController extends Controller
{
    /**
     * Get super admin dashboard data
     */
    public function dashboard(Request $request)
    {
        try {
            $user = $request->user();
            
            // Obtener estadísticas generales del sistema
            $stats = [
                'total_users' => User::count(),
                'total_organizations' => Organization::count(),
                'total_events' => Event::count(),
                'recent_users' => User::select('id', 'first_name', 'last_name', 'email', 'role_id', 'created_at')
                    ->with(['role' => fn($query) => $query->select('id', 'name')])
                    ->latest()
                    ->take(5)
                    ->get()
                    ->map(function ($user) {
                        return [
                            'id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                            'role' => $user->role ? $user->role->name : null,
                            'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                        ];
                    }),
                'recent_organizations' => Organization::select('id', 'name', 'slug', 'created_at')
                    ->latest()
                    ->take(5)
                    ->get()
                    ->map(function ($org) {
                        return [
                            'id' => $org->id,
                            'name' => $org->name,
                            'slug' => $org->slug,
                            'created_at' => $org->created_at->format('Y-m-d H:i:s'),
                        ];
                    }),
            ];

            // Log de acceso para auditoría
            Log::info('SuperAdmin dashboard accessed', [
                'user_id' => $user->id,
                'stats' => [
                    'total_users' => $stats['total_users'],
                    'total_organizations' => $stats['total_organizations'],
                    'total_events' => $stats['total_events'],
                ],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Super admin dashboard data retrieved successfully.',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role ? $user->role->name : null,
                    ],
                    'stats' => $stats,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error retrieving super admin dashboard data', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving super admin dashboard data.',
                'error' => app()->environment('local') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get all users with their roles and organizations
     */
    public function users(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search');

            $query = User::select('id', 'first_name', 'last_name', 'email', 'role_id', 'organization_id', 'created_at')
                ->with([
                    'role' => fn($query) => $query->select('id', 'name'),
                    'organization' => fn($query) => $query->select('id', 'name', 'slug'),
                ]);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $users = $query->orderBy('created_at', 'desc')
                          ->paginate($perPage);

            // Log de acceso para auditoría
            Log::info('SuperAdmin users list accessed', [
                'user_id' => $request->user()->id,
                'per_page' => $perPage,
                'search' => $search,
                'total' => $users->total(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Users retrieved successfully.',
                'data' => $users,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error retrieving users', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving users.',
                'error' => app()->environment('local') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get all organizations
     */
    public function organizations(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search');

            $query = Organization::select('id', 'name', 'slug', 'created_at')
                ->withCount(['users', 'events']);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('slug', 'like', "%{$search}%");
                });
            }

            $organizations = $query->orderBy('created_at', 'desc')
                                  ->paginate($perPage);

            // Log de acceso para auditoría
            Log::info('SuperAdmin organizations list accessed', [
                'user_id' => $request->user()->id,
                'per_page' => $perPage,
                'search' => $search,
                'total' => $organizations->total(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Organizations retrieved successfully.',
                'data' => $organizations,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error retrieving organizations', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving organizations.',
                'error' => app()->environment('local') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get system-wide events
     */
    public function events(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search');

            $query = Event::select('id', 'title', 'description', 'organization_id', 'creator_id', 'status_id', 'created_at')
                ->with([
                    'organization' => fn($query) => $query->select('id', 'name'),
                    'creator' => fn($query) => $query->select('id', 'first_name', 'last_name'),
                    'status' => fn($query) => $query->select('id', 'name'),
                ]);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $events = $query->orderBy('created_at', 'desc')
                           ->paginate($perPage);

            // Log de acceso para auditoría
            Log::info('SuperAdmin events list accessed', [
                'user_id' => $request->user()->id,
                'per_page' => $perPage,
                'search' => $search,
                'total' => $events->total(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Events retrieved successfully.',
                'data' => $events,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error retrieving events', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving events.',
                'error' => app()->environment('local') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Update user role (super admin only)
     */
    public function updateUserRole(Request $request, $userId)
    {
        try {
            $request->validate([
                'role_id' => 'required|exists:roles,id',
            ]);

            $currentUser = $request->user();
            $user = User::findOrFail($userId);
            $previousRoleId = $user->role_id;
            
            // Prevenir que el super admin se quite su propio rol
            if ($user->id === $currentUser->id && $request->role_id != $user->role_id) {
                Log::warning('SuperAdmin attempted to change own role', [
                    'user_id' => $currentUser->id,
                    'attempted_role_id' => $request->role_id,
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot change your own role.',
                ], 400);
            }

            // Verificar que no se asigne superadmin sin autorización adicional
            $role = Role::findOrFail($request->role_id);
            if ($role->name === 'superadmin' && !$currentUser->isSuperAdmin()) {
                Log::warning('Non-superadmin attempted to assign superadmin role', [
                    'user_id' => $currentUser->id,
                    'target_user_id' => $userId,
                    'attempted_role' => 'superadmin',
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot assign superadmin role.',
                ], 403);
            }

            // Usar transacción para garantizar consistencia
            DB::transaction(function () use ($user, $request, $currentUser, $previousRoleId, $role) {
                $user->role_id = $request->role_id;
                $user->save();

                // Log de auditoría detallado
                Log::info('User role updated by SuperAdmin', [
                    'admin_user_id' => $currentUser->id,
                    'admin_email' => $currentUser->email,
                    'target_user_id' => $user->id,
                    'target_email' => $user->email,
                    'previous_role_id' => $previousRoleId,
                    'new_role_id' => $request->role_id,
                    'new_role_name' => $role->name,
                    'timestamp' => now()->toISOString(),
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'User role updated successfully.',
                'data' => [
                    'user' => $user->load(['role' => fn($query) => $query->select('id', 'name')]),
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error updating user role', [
                'error' => $e->getMessage(),
                'admin_user_id' => $request->user()?->id,
                'target_user_id' => $userId,
                'attempted_role_id' => $request->input('role_id'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error updating user role.',
                'error' => app()->environment('local') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
