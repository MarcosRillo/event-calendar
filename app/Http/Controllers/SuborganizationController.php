<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\CreateSuborganizationRequest;
use App\Models\Invitation;
use App\Models\InvitationOrganizationData;
use App\Models\InvitationAdminData;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Exception;

class SuborganizationController extends Controller
{
    /**
     * Create a new sub-organization request using FormRequest validation
     */
    public function createRequest(CreateSuborganizationRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // The validation is handled by CreateSuborganizationRequest
            $validatedData = $request->validated();

            // Log request initiation
            Log::info('Sub-organization request creation initiated', [
                'user_id' => Auth::id(),
                'data' => $validatedData
            ]);

            // Create the invitation
            $invitation = Invitation::create([
                'id' => Str::uuid(),
                'invited_user_id' => Auth::id(),
                'invitation_status_id' => 1, // Pending
                'created_at' => now(),
                'updated_at' => now(),
                'expires_at' => now()->addDays(30), // 30 days expiration
            ]);

            // Create organization data
            $organizationData = InvitationOrganizationData::create([
                'invitation_id' => $invitation->id,
                'organization_name' => $validatedData['organization_name'],
                'organization_slug' => $validatedData['organization_slug'],
                'organization_address' => $validatedData['organization_address'],
                'organization_phone' => $validatedData['organization_phone'],
                'organization_logo' => $validatedData['organization_logo'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create admin data
            $adminData = InvitationAdminData::create([
                'invitation_id' => $invitation->id,
                'admin_name' => $validatedData['admin_name'],
                'admin_last_name' => $validatedData['admin_last_name'],
                'admin_email' => $validatedData['admin_email'],
                'admin_phone' => $validatedData['admin_phone'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            Log::info('Sub-organization request created successfully', [
                'invitation_id' => $invitation->id,
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Solicitud de sub-organización creada exitosamente. Será revisada por un administrador.',
                'data' => [
                    'invitation' => $invitation,
                    'organization' => $organizationData,
                    'admin' => $adminData
                ]
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to create sub-organization request', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear la solicitud de sub-organización. Por favor, inténtalo de nuevo.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Crear una solicitud de sub-organización
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function requestSuborganization(Request $request): JsonResponse
    {
        try {
            // Validar datos de entrada
            $data = $request->validate([
                'email' => ['required', 'email'],
                'organization' => ['required', 'array'],
                'organization.name' => ['required', 'string', 'max:255'],
                'organization.slug' => ['required', 'string', 'max:100', 'unique:invitation_organization_data,slug'],
                'organization.website_url' => ['nullable', 'url', 'max:255'],
                'organization.address' => ['nullable', 'string', 'max:500'],
                'organization.phone' => ['nullable', 'string', 'max:20'],
                'organization.email' => ['nullable', 'email', 'max:255'],
                'admin' => ['required', 'array'],
                'admin.first_name' => ['required', 'string', 'max:100'],
                'admin.last_name' => ['required', 'string', 'max:100'],
                'admin.email' => ['required', 'email', 'max:255'],
                'admin.phone' => ['nullable', 'string', 'max:20'],
            ]);

            // Verificar que el usuario autenticado tenga permisos
            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado',
                    'error' => 'UNAUTHORIZED'
                ], 401);
            }

            // Usar transacción para asegurar consistencia de datos
            $result = DB::transaction(function () use ($data) {
                // Crear la invitación
                $invitation = Invitation::create([
                    'email' => $data['email'],
                    'token' => Str::random(40),
                    'status_id' => 1, // pending
                    'created_by' => Auth::id(),
                ]);

                // Crear datos de la organización
                $organizationData = InvitationOrganizationData::create([
                    'invitation_id' => $invitation->id,
                    'name' => $data['organization']['name'],
                    'slug' => $data['organization']['slug'],
                    'website_url' => $data['organization']['website_url'] ?? null,
                    'address' => $data['organization']['address'] ?? null,
                    'phone' => $data['organization']['phone'] ?? null,
                    'email' => $data['organization']['email'] ?? null,
                ]);

                // Crear datos del administrador
                $adminData = InvitationAdminData::create([
                    'invitation_id' => $invitation->id,
                    'first_name' => $data['admin']['first_name'],
                    'last_name' => $data['admin']['last_name'],
                    'email' => $data['admin']['email'],
                    'phone' => $data['admin']['phone'] ?? null,
                ]);

                return [
                    'invitation' => $invitation,
                    'organization_data' => $organizationData,
                    'admin_data' => $adminData
                ];
            });

            // Log de la operación exitosa
            Log::info('Solicitud de sub-organización creada exitosamente', [
                'invitation_id' => $result['invitation']->id,
                'organization_name' => $data['organization']['name'],
                'created_by' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Solicitud de sub-organización creada exitosamente',
                'data' => [
                    'invitation_id' => $result['invitation']->id,
                    'token' => $result['invitation']->token,
                    'organization_name' => $data['organization']['name'],
                    'status' => 'pending'
                ]
            ], 201);

        } catch (Exception $e) {
            // Log del error
            Log::error('Error al crear solicitud de sub-organización', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'request_data' => $request->except(['admin.password']) // Excluir datos sensibles
            ]);

            // Respuesta de error para el usuario
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor. Por favor, inténtelo nuevamente.',
                'error' => 'INTERNAL_SERVER_ERROR'
            ], 500);
        }
    }

    /**
     * Listar solicitudes de sub-organización (para administradores)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function listRequests(Request $request): JsonResponse
    {
        try {
            // Verificar permisos
            if (!Auth::check() || !Auth::user()->is_super_admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para acceder a esta información',
                    'error' => 'FORBIDDEN'
                ], 403);
            }

            $invitations = Invitation::with(['organizationData', 'adminData', 'status'])
                ->where('created_by', '!=', null) // Solo invitaciones de sub-organizaciones
                ->orderBy('created_at', 'desc')
                ->paginate($request->input('per_page', 15));

            return response()->json([
                'success' => true,
                'message' => 'Solicitudes obtenidas exitosamente',
                'data' => $invitations
            ]);

        } catch (Exception $e) {
            Log::error('Error al obtener solicitudes de sub-organización', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las solicitudes',
                'error' => 'INTERNAL_SERVER_ERROR'
            ], 500);
        }
    }

    /**
     * Aprobar o rechazar una solicitud de sub-organización
     * 
     * @param Request $request
     * @param int $invitationId
     * @return JsonResponse
     */
    public function updateRequestStatus(Request $request, int $invitationId): JsonResponse
    {
        try {
            // Verificar permisos
            if (!Auth::check() || !Auth::user()->is_super_admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para realizar esta acción',
                    'error' => 'FORBIDDEN'
                ], 403);
            }

            $data = $request->validate([
                'status' => ['required', 'in:approved,rejected'],
                'reason' => ['nullable', 'string', 'max:500']
            ]);

            $invitation = Invitation::with(['organizationData', 'adminData'])->findOrFail($invitationId);

            // Actualizar estado
            $statusId = $data['status'] === 'approved' ? 2 : 3; // 2 = approved, 3 = rejected
            $invitation->update([
                'status_id' => $statusId,
                'processed_by' => Auth::id(),
                'processed_at' => now(),
                'rejection_reason' => $data['reason'] ?? null
            ]);

            Log::info('Estado de solicitud de sub-organización actualizado', [
                'invitation_id' => $invitationId,
                'new_status' => $data['status'],
                'processed_by' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => $data['status'] === 'approved' 
                    ? 'Solicitud aprobada exitosamente' 
                    : 'Solicitud rechazada exitosamente',
                'data' => [
                    'invitation_id' => $invitationId,
                    'status' => $data['status'],
                    'processed_at' => now()->toISOString()
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Error al actualizar estado de solicitud', [
                'error' => $e->getMessage(),
                'invitation_id' => $invitationId,
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el estado de la solicitud',
                'error' => 'INTERNAL_SERVER_ERROR'
            ], 500);
        }
    }
}
