<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SuborganizationController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationRequestPublicController;
use App\Http\Controllers\OrganizationRequestStatusController;
use App\Http\Controllers\OrganizationRequestAdminController;

Route::middleware(['throttle:5,1'])->post('/login', [AuthController::class, 'login']);
Route::post('/request-suborganization', [SuborganizationController::class, 'requestSuborganization']);

// Public routes for organization requests
Route::prefix('organization-request')->group(function () {
    Route::get('/verify/{token}', [OrganizationRequestPublicController::class, 'verifyToken']);
    Route::post('/submit/{token}', [OrganizationRequestPublicController::class, 'submitRequest']);
});

// Public route for anyone to submit organization requests
Route::post('/organization-requests', [OrganizationRequestPublicController::class, 'createRequest']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // Sub-organization routes
    Route::post('/suborganization/create', [SuborganizationController::class, 'createRequest']);
    
    Route::get('/user', function (Request $request) {
        try {
            $user = $request->user();
            $user->load('role'); // Cargar la relación role
            
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
                'message' => 'User data retrieved successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving user data.',
                'error' => app()->environment('local') ? $e->getMessage() : null,
            ], 500);
        }
    });
});

// Super Admin Routes
Route::prefix('super-admin')->middleware(['auth:sanctum', 'super-admin'])->group(function () {
    Route::get('/dashboard', [SuperAdminController::class, 'dashboard']);
    Route::get('/users', [SuperAdminController::class, 'users']);
    Route::get('/users/{userId}', [SuperAdminController::class, 'showUser']); // Show specific user
    Route::put('/users/{userId}', [SuperAdminController::class, 'updateUser']); // Update user
    Route::patch('/users/{userId}/toggle-status', [SuperAdminController::class, 'toggleUserStatus']); // Toggle user status
    Route::delete('/users/{userId}', [SuperAdminController::class, 'deleteUser']); // Soft delete user
    Route::get('/roles', [SuperAdminController::class, 'getRoles']); // Get all roles
    Route::get('/organizations', [SuperAdminController::class, 'organizations']);
    Route::get('/events', [SuperAdminController::class, 'events']);
    Route::put('/users/{userId}/role', [SuperAdminController::class, 'updateUserRole']);
    
    // Organizations CRUD management - using different prefix to avoid conflict
    Route::prefix('organizations-management')->group(function () {
        Route::get('/', [OrganizationController::class, 'index']);                    // List organizations
        Route::get('/{organization}', [OrganizationController::class, 'show']);       // Show organization
        Route::put('/{organization}', [OrganizationController::class, 'update']);     // Update organization
        Route::delete('/{organization}', [OrganizationController::class, 'destroy']); // Soft delete
        Route::post('/{organization}/restore', [OrganizationController::class, 'restore']); // Restore soft deleted
        Route::delete('/{organization}/force', [OrganizationController::class, 'forceDestroy']); // Permanent delete
        Route::patch('/{organization}/toggle-status', [OrganizationController::class, 'toggleStatus']); // Toggle active status
        Route::put('/{organization}/admin', [OrganizationController::class, 'updateAdmin']); // Update organization admin
    });
    
    // Organization requests management
    Route::prefix('organization-requests')->group(function () {
        Route::post('/send-invitation', [OrganizationRequestAdminController::class, 'sendInvitation']);
        Route::get('/', [OrganizationRequestAdminController::class, 'listRequests']);
        Route::get('/statistics', [OrganizationRequestAdminController::class, 'getStatistics']);
        Route::get('/{invitationId}', [OrganizationRequestAdminController::class, 'getRequest']);
        Route::put('/{invitationId}/status', [OrganizationRequestStatusController::class, 'updateStatus']);
        Route::patch('/{invitationId}/status', [OrganizationRequestStatusController::class, 'updateStatus']);
    });
});
