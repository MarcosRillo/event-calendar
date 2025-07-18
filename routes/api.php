<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SuborganizationController;
use App\Http\Controllers\SuperAdminController;

Route::middleware(['throttle:60,1'])->post('/login', [AuthController::class, 'login']);
Route::post('/request-suborganization', [SuborganizationController::class, 'requestSuborganization']);

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
    Route::get('/organizations', [SuperAdminController::class, 'organizations']);
    Route::get('/events', [SuperAdminController::class, 'events']);
    Route::put('/users/{userId}/role', [SuperAdminController::class, 'updateUserRole']);
});
