<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\Organization;
use App\Models\User;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Http\Requests\UpdateOrganizationAdminRequest;
use App\Services\CacheService;

class OrganizationController extends Controller
{
    private CacheService $cacheService;

    public function __construct(CacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Display a listing of organizations.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search');
            $status = $request->get('status'); // active, inactive, deleted
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');

            $query = Organization::with(['admin', 'trustLevel', 'createdBy'])
                ->withCount(['users', 'events']);

            // Handle trashed/soft deleted organizations
            if ($status === 'deleted') {
                $query->onlyTrashed();
            } elseif ($status === 'all') {
                $query->withTrashed();
            } else {
                // Default: only non-deleted
                if ($status === 'inactive') {
                    $query->where('is_active', false);
                } elseif ($status === 'active') {
                    $query->where('is_active', true);
                }
            }

            // Search functionality
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('slug', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhereHas('admin', function ($adminQuery) use ($search) {
                          $adminQuery->where('first_name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                      });
                });
            }

            // Sorting
            if (in_array($sortBy, ['name', 'created_at', 'updated_at', 'is_active'])) {
                $query->orderBy($sortBy, $sortDirection);
            } else {
                $query->orderBy('created_at', 'desc');
            }

            $organizations = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Organizations retrieved successfully.',
                'data' => $organizations->items(),
                'meta' => [
                    'current_page' => $organizations->currentPage(),
                    'last_page' => $organizations->lastPage(),
                    'per_page' => $organizations->perPage(),
                    'total' => $organizations->total(),
                    'from' => $organizations->firstItem(),
                    'to' => $organizations->lastItem(),
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error retrieving organizations', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving organizations.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified organization.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $organization = Organization::with(['admin', 'trustLevel', 'createdBy', 'users', 'events'])
                ->withCount(['users', 'events'])
                ->withTrashed()
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Organization retrieved successfully.',
                'data' => $organization
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error retrieving organization', [
                'organization_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Organization not found.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 404);
        }
    }

    /**
     * Update the specified organization.
     */
    public function update(UpdateOrganizationRequest $request, string $id): JsonResponse
    {
        try {
            $organization = Organization::withTrashed()->findOrFail($id);
            
            // Check if organization is soft deleted
            if ($organization->trashed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update a deleted organization. Restore it first.'
                ], 422);
            }

            DB::beginTransaction();

            $data = $request->validated();
            
            // Update organization
            $organization->update($data);

            // Clear cache
            $this->cacheService->forget($this->cacheService->getStatsKey('dashboard'));
            $this->cacheService->forgetByPrefix('organizations:');

            DB::commit();

            // Reload with relationships
            $organization->load(['admin', 'trustLevel', 'createdBy'])
                         ->loadCount(['users', 'events']);

            Log::info('Organization updated successfully', [
                'organization_id' => $organization->id,
                'updated_by' => Auth::id(),
                'changes' => $organization->getChanges()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Organization updated successfully.',
                'data' => $organization
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error updating organization', [
                'organization_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error updating organization.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Soft delete the specified organization.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $organization = Organization::with(['admin'])->findOrFail($id);

            DB::beginTransaction();

            // Soft delete the organization
            $organization->delete();

            // Soft delete the admin user if exists
            if ($organization->admin) {
                $organization->admin->delete();
                Log::info('Admin user soft deleted with organization', [
                    'admin_id' => $organization->admin->id,
                    'organization_id' => $organization->id
                ]);
            }

            // Deactivate all other associated users
            $organization->users()->where('id', '!=', $organization->admin_id ?? 0)
                          ->update(['is_active' => false]);

            // Clear cache
            $this->cacheService->forget($this->cacheService->getStatsKey('dashboard'));
            $this->cacheService->forgetByPrefix('organizations:');

            DB::commit();

            Log::info('Organization soft deleted', [
                'organization_id' => $organization->id,
                'deleted_by' => Auth::id(),
                'users_deactivated' => $organization->users()->count()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Organization deleted successfully. It can be restored later.'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error soft deleting organization', [
                'organization_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error deleting organization.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Restore a soft-deleted organization.
     */
    public function restore(string $id): JsonResponse
    {
        try {
            $organization = Organization::onlyTrashed()->with(['admin' => function($query) {
                $query->onlyTrashed();
            }])->findOrFail($id);

            DB::beginTransaction();

            // Restore the organization
            $organization->restore();

            // Restore and reactivate the admin user if exists
            if ($organization->admin) {
                $organization->admin->restore();
                $organization->admin->update(['is_active' => true]);
                Log::info('Admin user restored with organization', [
                    'admin_id' => $organization->admin->id,
                    'organization_id' => $organization->id
                ]);
            }

            // Reactivate other associated users (but don't restore if soft deleted independently)
            $organization->users()->where('id', '!=', $organization->admin_id ?? 0)
                          ->where('deleted_at', null)
                          ->update(['is_active' => true]);

            // Clear cache
            $this->cacheService->forget($this->cacheService->getStatsKey('dashboard'));
            $this->cacheService->forgetByPrefix('organizations:');

            DB::commit();

            Log::info('Organization restored', [
                'organization_id' => $organization->id,
                'restored_by' => Auth::id(),
                'users_reactivated' => $organization->users()->count()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Organization restored successfully.',
                'data' => $organization
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error restoring organization', [
                'organization_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error restoring organization.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Permanently delete the specified organization.
     */
    public function forceDestroy(string $id): JsonResponse
    {
        try {
            $organization = Organization::onlyTrashed()->with(['admin' => function($query) {
                $query->onlyTrashed();
            }])->findOrFail($id);

            DB::beginTransaction();

            // Store admin ID for logging before deletion
            $adminId = $organization->admin ? $organization->admin->id : null;

            // Permanently delete the admin user first if exists
            if ($organization->admin) {
                $organization->admin->forceDelete();
                Log::warning('Admin user permanently deleted with organization', [
                    'admin_id' => $adminId,
                    'organization_id' => $organization->id
                ]);
            }

            // Permanently delete other related data
            $organization->users()->where('id', '!=', $adminId ?? 0)->forceDelete();
            $organization->events()->forceDelete();
            $organization->styles()->delete();
            $organization->formFields()->delete();

            // Permanently delete the organization
            $organization->forceDelete();

            // Clear cache
            $this->cacheService->forget($this->cacheService->getStatsKey('dashboard'));
            $this->cacheService->forgetByPrefix('organizations:');

            DB::commit();

            Log::warning('Organization permanently deleted', [
                'organization_id' => $id,
                'deleted_by' => Auth::id(),
                'action' => 'PERMANENT_DELETE'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Organization permanently deleted. This action cannot be undone.'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error permanently deleting organization', [
                'organization_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error permanently deleting organization.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Toggle organization active status.
     */
    public function toggleStatus(string $id): JsonResponse
    {
        try {
            $organization = Organization::with(['admin'])->findOrFail($id);

            DB::beginTransaction();

            $newStatus = !$organization->is_active;
            $organization->update(['is_active' => $newStatus]);

            // Update admin user status accordingly if exists
            if ($organization->admin) {
                $organization->admin->update(['is_active' => $newStatus]);
                Log::info('Admin user status updated with organization', [
                    'admin_id' => $organization->admin->id,
                    'new_status' => $newStatus ? 'active' : 'inactive'
                ]);
            }

            // Update other associated users status accordingly
            $organization->users()->where('id', '!=', $organization->admin_id ?? 0)
                          ->update(['is_active' => $newStatus]);

            // Clear cache
            $this->cacheService->forget($this->cacheService->getStatsKey('dashboard'));
            $this->cacheService->forgetByPrefix('organizations:');

            DB::commit();

            Log::info('Organization status toggled', [
                'organization_id' => $organization->id,
                'new_status' => $newStatus ? 'active' : 'inactive',
                'updated_by' => Auth::id(),
                'users_affected' => $organization->users()->count()
            ]);

            return response()->json([
                'success' => true,
                'message' => $newStatus ? 'Organization activated successfully.' : 'Organization deactivated successfully.',
                'data' => [
                    'id' => $organization->id,
                    'is_active' => $organization->is_active
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error toggling organization status', [
                'organization_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error updating organization status.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update the admin user of an organization.
     */
    public function updateAdmin(string $id, UpdateOrganizationAdminRequest $request): JsonResponse
    {
        try {
            $organization = Organization::with(['admin'])->findOrFail($id);

            // Get validated data
            $validated = $request->getValidatedData();

            DB::beginTransaction();

            if (!$organization->admin) {
                // If no admin exists, we need to create one
                $adminRole = \App\Models\Role::where('name', 'organization_admin')->first();
                if (!$adminRole) {
                    throw new \Exception('Organization admin role not found');
                }

                $userData = [
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'] ?? null,
                    'role_id' => $adminRole->id,
                    'organization_id' => $organization->id,
                    'is_active' => $organization->is_active,
                    'created_by' => Auth::id(),
                ];

                if (!empty($validated['password'])) {
                    $userData['password'] = bcrypt($validated['password']);
                } else {
                    $userData['password'] = bcrypt('temp_password_' . time());
                }

                $admin = \App\Models\User::create($userData);
                $organization->update(['admin_id' => $admin->id]);

                Log::info('New admin user created for organization', [
                    'admin_id' => $admin->id,
                    'organization_id' => $organization->id,
                    'created_by' => Auth::id()
                ]);

                $message = 'Admin user created and assigned successfully.';
            } else {
                // Update existing admin
                $updateData = [
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'] ?? null,
                ];

                if (!empty($validated['password'])) {
                    $updateData['password'] = bcrypt($validated['password']);
                }

                $organization->admin->update($updateData);

                Log::info('Admin user updated for organization', [
                    'admin_id' => $organization->admin->id,
                    'organization_id' => $organization->id,
                    'updated_by' => Auth::id(),
                    'changes' => $organization->admin->getChanges()
                ]);

                $message = 'Admin user updated successfully.';
                $admin = $organization->admin;
            }

            // Clear cache
            $this->cacheService->forgetByPrefix('organizations:');
            $this->cacheService->forgetByPrefix('users:');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'admin' => $admin->fresh(['role', 'organization']),
                    'organization' => $organization->fresh(['admin', 'trustLevel'])
                ]
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error updating organization admin', [
                'organization_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error updating admin user.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
