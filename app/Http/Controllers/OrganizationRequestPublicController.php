<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateOrganizationRequestRequest;
use App\Models\Invitation;
use App\Models\InvitationAdminData;
use App\Models\InvitationOrganizationData;
use App\Services\EmailService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrganizationRequestPublicController extends Controller
{
    private EmailService $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * Crear una solicitud pública de organización
     */
    public function createRequest(CreateOrganizationRequestRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            DB::beginTransaction();

            // Crear la invitación
            $invitation = Invitation::create([
                'email' => $data['email'],
                'token' => \Illuminate\Support\Str::random(64),
                'status_id' => 2, // pending
                'expires_at' => now()->addDays(7),
                'created_by' => 1
            ]);

            // Crear datos de organización
            InvitationOrganizationData::create([
                'invitation_id' => $invitation->id,
                'name' => $data['organization_name'],
                'slug' => \Illuminate\Support\Str::slug($data['organization_name']),
                'description' => $data['description'],
                'website_url' => $data['website'] ?? null,
                'address' => $data['address'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email']
            ]);

            // Crear datos del admin
            $nameParts = explode(' ', $data['contact_name'], 2);
            InvitationAdminData::create([
                'invitation_id' => $invitation->id,
                'first_name' => $nameParts[0],
                'last_name' => $nameParts[1] ?? '',
                'email' => $data['email']
            ]);

            DB::commit();

            // Send email notification
            try {
                $emailSent = $this->emailService->sendOrganizationInvitation($invitation);
                
                // Log::info('Welcome email queued', [
                //     'invitation_id' => $invitation->id,
                //     'email' => $data['email'],
                //     'result' => $emailSent
                // ]);
            } catch (\Exception $e) {
                // Log::error('Failed to send welcome email', [
                //     'invitation_id' => $invitation->id,
                //     'error' => $e->getMessage()
                // ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Solicitud enviada exitosamente. Recibirás un email con instrucciones.',
                'data' => [
                    'invitation_id' => $invitation->id
                ]
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('Error al crear solicitud pública', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud'
            ], 500);
        }
    }

    /**
     * Verificar token de invitación
     */
    public function verifyToken(string $token): JsonResponse
    {
        try {
            $invitation = Invitation::where('token', $token)
                ->whereIn('status_id', [1, 2, 5]) // sent, pending, corrections_needed
                ->where('expires_at', '>', now())
                ->first();

            if (!$invitation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token inválido o expirado',
                    'error' => 'INVALID_TOKEN'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Token válido',
                'data' => [
                    'email' => $invitation->email,
                    'expires_at' => $invitation->expires_at->toISOString(),
                    'is_expired' => $invitation->expires_at->isPast()
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Error al verificar token', [
                'token' => $token,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al verificar el token',
                'error' => 'INTERNAL_SERVER_ERROR'
            ], 500);
        }
    }

    /**
     * Enviar datos de organización con token
     */
    public function submitRequest(Request $request, string $token): JsonResponse
    {
        try {
            // Verificar token
            $invitation = Invitation::where('token', $token)
                ->whereIn('status_id', [1, 2, 5]) // sent, pending, corrections_needed
                ->where('expires_at', '>', now())
                ->first();

            if (!$invitation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token inválido o expirado',
                    'error' => 'INVALID_TOKEN'
                ], 404);
            }

            // Validar datos de la solicitud
            $data = $request->validate([
                'organization.name' => ['required', 'string', 'max:255'],
                'organization.slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9\-]+$/'],
                'organization.website_url' => ['nullable', 'url', 'max:255'],
                'organization.address' => ['nullable', 'string', 'max:500'],
                'organization.phone' => ['nullable', 'string', 'max:20'],
                'organization.email' => ['nullable', 'email', 'max:255'],
                'admin.first_name' => ['required', 'string', 'max:100'],
                'admin.last_name' => ['required', 'string', 'max:100'],
                'admin.email' => ['required', 'email', 'max:255']
            ]);

            DB::beginTransaction();

            // Actualizar datos de organización
            $invitation->organizationData()->updateOrCreate(
                ['invitation_id' => $invitation->id],
                $data['organization']
            );

            // Actualizar datos del admin
            $invitation->adminData()->updateOrCreate(
                ['invitation_id' => $invitation->id],
                $data['admin']
            );

            // Actualizar estado a pending si estaba en sent o corrections_needed
            if (in_array($invitation->status_id, [1, 5])) { // sent o corrections_needed
                $invitation->update([
                    'status_id' => 2, // pending
                    'accepted_at' => now()
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Datos enviados exitosamente. Tu solicitud será revisada.',
                'data' => [
                    'invitation_id' => $invitation->id,
                    'status' => $invitation->status->name
                ]
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('Error al enviar solicitud', [
                'token' => $token,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud'
            ], 500);
        }
    }
}
