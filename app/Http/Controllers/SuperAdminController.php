<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Organization;
use App\Models\Event;
use App\Models\Role;
use App\Services\CacheService;
use App\Http\Requests\UpdateUserRequest;

class SuperAdminController extends Controller
{
    private CacheService $cacheService;

    public function __construct(CacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Get super admin dashboard data
     */
    public function dashboard(Request $request)
    {
        try {
            $user = $request->user();
            
            // Cache dashboard data for 10 minutes
            $stats = $this->cacheService->remember(
                $this->cacheService->getStatsKey('dashboard'),
                CacheService::TTL_SHORT * 2, // 10 minutes
                function () {
                    return $this->getDashboardStats();
                }
            );

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
     * Get dashboard statistics with optimized queries
     */
    private function getDashboardStats(): array
    {
        return [
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
    }

    /**
     * Get all users with their roles and organizations
     */
    public function users(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search');

            $query = User::select('id', 'first_name', 'last_name', 'email', 'phone', 'role_id', 'organization_id', 'is_active', 'created_at')
                ->with([
                    'role' => fn($query) => $query->select('id', 'name'),
                    'organization' => fn($query) => $query->select('id', 'name', 'slug'),
                ]);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $users = $query->orderBy('created_at', 'desc')
                          ->paginate($perPage);

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

            $query = Event::select('id', 'title', 'description', 'start_date', 'end_date', 'organization_id', 'created_by', 'status_id', 'created_at')
                ->with([
                    'organization' => fn($query) => $query->select('id', 'name', 'slug'),
                    'createdBy' => fn($query) => $query->select('id', 'first_name', 'last_name'),
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

    /**
     * Show a specific user
     */
    public function showUser(Request $request, $userId)
    {
        try {
            $user = User::with([
                'role' => fn($query) => $query->select('id', 'name'),
                'organization' => fn($query) => $query->select('id', 'name', 'slug'),
                'createdBy' => fn($query) => $query->select('id', 'first_name', 'last_name')
            ])->findOrFail($userId);

            return response()->json([
                'success' => true,
                'message' => 'User retrieved successfully.',
                'data' => [
                    'user' => $user
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error retrieving user', [
                'error' => $e->getMessage(),
                'admin_user_id' => $request->user()?->id,
                'target_user_id' => $userId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving user.',
                'error' => app()->environment('local') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Update user information
     */
    public function updateUser(UpdateUserRequest $request, $userId)
    {
        try {
            $currentUser = $request->user();
            $user = User::findOrFail($userId);
            $originalData = $user->toArray();
            
            // Prevenir que el super admin se desactive a sí mismo o cambie su rol a no-superadmin
            if ($user->id === $currentUser->id) {
                if (isset($request->is_active) && !$request->is_active) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No puedes desactivarte a ti mismo.',
                    ], 400);
                }

                if (isset($request->role_id)) {
                    $newRole = Role::find($request->role_id);
                    if ($newRole && $newRole->name !== 'superadmin') {
                        return response()->json([
                            'success' => false,
                            'message' => 'No puedes cambiar tu propio rol de superadmin.',
                        ], 400);
                    }
                }
            }

            // Verificar que no se asigne superadmin sin autorización adicional
            if (isset($request->role_id)) {
                $role = Role::findOrFail($request->role_id);
                if ($role->name === 'superadmin' && !$currentUser->isSuperAdmin()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No tienes permisos para asignar el rol de superadmin.',
                    ], 403);
                }
            }

            // Usar transacción para garantizar consistencia
            DB::transaction(function () use ($user, $request, $currentUser, $originalData) {
                $validatedData = $request->getValidatedData();
                
                // Actualizar los campos
                $user->fill($validatedData);
                $user->save();

                // Limpiar caché relacionado
                $this->cacheService->forgetByPrefix('stats:');
                $this->cacheService->forgetByPrefix('dashboard:');
                $this->cacheService->forgetByPrefix('users:');

                // Log de auditoría detallado
                Log::info('User updated by SuperAdmin', [
                    'admin_user_id' => $currentUser->id,
                    'admin_email' => $currentUser->email,
                    'target_user_id' => $user->id,
                    'target_email' => $user->email,
                    'original_data' => $originalData,
                    'updated_data' => $user->toArray(),
                    'timestamp' => now()->toISOString(),
                ]);
            });

            // Cargar relaciones actualizadas
            $user->load([
                'role' => fn($query) => $query->select('id', 'name'),
                'organization' => fn($query) => $query->select('id', 'name', 'slug')
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Usuario actualizado exitosamente.',
                'data' => [
                    'user' => $user
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error updating user', [
                'error' => $e->getMessage(),
                'admin_user_id' => $request->user()?->id,
                'target_user_id' => $userId,
                'request_data' => $request->except(['password', 'password_confirmation']),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el usuario.',
                'error' => app()->environment('local') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Toggle user active status
     */
    public function toggleUserStatus(Request $request, $userId)
    {
        try {
            $currentUser = $request->user();
            $user = User::findOrFail($userId);
            
            // Prevenir que el super admin se desactive a sí mismo
            if ($user->id === $currentUser->id && $user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'No puedes desactivarte a ti mismo.',
                ], 400);
            }

            $previousStatus = $user->is_active;
            $newStatus = !$user->is_active;

            DB::transaction(function () use ($user, $currentUser, $previousStatus, $newStatus) {
                $user->is_active = $newStatus;
                $user->save();

                // Limpiar caché relacionado
                $this->cacheService->forgetByPrefix('stats:');
                $this->cacheService->forgetByPrefix('dashboard:');
                $this->cacheService->forgetByPrefix('users:');

                Log::info('User status toggled by SuperAdmin', [
                    'admin_user_id' => $currentUser->id,
                    'admin_email' => $currentUser->email,
                    'target_user_id' => $user->id,
                    'target_email' => $user->email,
                    'previous_status' => $previousStatus,
                    'new_status' => $newStatus,
                    'timestamp' => now()->toISOString(),
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => $newStatus ? 'Usuario activado exitosamente.' : 'Usuario desactivado exitosamente.',
                'data' => [
                    'user' => $user->load([
                        'role' => fn($query) => $query->select('id', 'name'),
                        'organization' => fn($query) => $query->select('id', 'name', 'slug')
                    ])
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error toggling user status', [
                'error' => $e->getMessage(),
                'admin_user_id' => $request->user()?->id,
                'target_user_id' => $userId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar el estado del usuario.',
                'error' => app()->environment('local') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Soft delete a user
     */
    public function deleteUser(Request $request, $userId)
    {
        try {
            $currentUser = $request->user();
            $user = User::with('role')->findOrFail($userId);
            
            // Prevenir que el super admin se elimine a sí mismo
            if ($user->id === $currentUser->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No puedes eliminarte a ti mismo.',
                ], 400);
            }

            // Validación explícita: No permitir eliminar usuarios administradores
            if ($user->role && in_array($user->role->name, ['superadmin', 'admin'])) {
                $roleLabel = $user->role->name === 'superadmin' ? 'super administrador' : 'administrador de organización';
                return response()->json([
                    'success' => false,
                    'message' => "No se puede eliminar un usuario {$roleLabel}.",
                ], 403);
            }

            // Verificar si el usuario es admin de alguna organización (validación adicional)
            $adminedOrganizations = $user->administeredOrganizations()->count();
            if ($adminedOrganizations > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar el usuario porque es administrador de una o más organizaciones.',
                ], 400);
            }

            DB::transaction(function () use ($user, $currentUser) {
                $user->delete();

                // Limpiar caché relacionado
                $this->cacheService->forgetByPrefix('stats:');
                $this->cacheService->forgetByPrefix('dashboard:');
                $this->cacheService->forgetByPrefix('users:');

                Log::info('User soft deleted by SuperAdmin', [
                    'admin_user_id' => $currentUser->id,
                    'admin_email' => $currentUser->email,
                    'deleted_user_id' => $user->id,
                    'deleted_user_email' => $user->email,
                    'timestamp' => now()->toISOString(),
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Usuario eliminado exitosamente.',
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error deleting user', [
                'error' => $e->getMessage(),
                'admin_user_id' => $request->user()?->id,
                'target_user_id' => $userId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el usuario.',
                'error' => app()->environment('local') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get all available roles
     */
    public function getRoles(Request $request)
    {
        try {
            $roles = Role::select('id', 'name')->orderBy('name')->get();

            return response()->json([
                'success' => true,
                'message' => 'Roles retrieved successfully.',
                'data' => [
                    'roles' => $roles
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error retrieving roles', [
                'error' => $e->getMessage(),
                'admin_user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving roles.',
                'error' => app()->environment('local') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
