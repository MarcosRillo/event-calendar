<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOrganizationStatusRequest;
use App\Models\Invitation;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\EmailService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class OrganizationRequestStatusController extends Controller
{
    private EmailService $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * Actualizar el estado de una solicitud de organización
     */
    public function updateStatus(UpdateOrganizationStatusRequest $request, int $invitationId): JsonResponse
    {
        try {
            $data = $request->validated();

            DB::beginTransaction();

            $invitation = Invitation::with(['organizationData', 'adminData'])->findOrFail($invitationId);

            switch ($data['action']) {
                case 'approve':
                    return $this->approveRequest($invitation, $data['message'] ?? null);
                case 'reject':
                    return $this->rejectRequest($invitation, $data['message'] ?? null);
                case 'request_corrections':
                    return $this->requestCorrections($invitation, $data['corrections_notes']);
                default:
                    throw new Exception('Acción no válida');
            }

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error actualizando estado de solicitud', [
                'invitation_id' => $invitationId,
                'error' => $e->getMessage(),
                'action' => $data['action'] ?? 'unknown'
            ]);

            return response()->json([
                'message' => 'Error al actualizar el estado de la solicitud',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Aprobar una solicitud de organización
     */
    private function approveRequest(Invitation $invitation, ?string $message): JsonResponse
    {
        try {
            // Crear la organización
            $organizationData = $invitation->organizationData;
            $adminData = $invitation->adminData;

            $organization = Organization::create([
                'name' => $organizationData->name,
                'slug' => $organizationData->slug,
                'description' => null, // No hay description en InvitationOrganizationData
                'website_url' => $organizationData->website_url,
                'address' => $organizationData->address,
                'phone' => $organizationData->phone,
                'email' => $organizationData->email,
                'status' => 'active',
                'created_by' => Auth::id()
            ]);

            // Crear el usuario admin
            $user = User::create([
                'first_name' => $adminData->first_name,
                'last_name' => $adminData->last_name,
                'email' => $adminData->email,
                'password' => Hash::make(\Illuminate\Support\Str::random(12)), // Temporal
                'role_id' => Role::where('name', 'admin')->first()->id,
                'organization_id' => $organization->id,
                'email_verified_at' => now()
            ]);

            // Actualizar estado de la invitación
            $invitation->update([
                'status_id' => 3, // approved
                'processed_at' => now(),
                'processed_by' => Auth::id()
            ]);

            // Crear notificación
            Notification::create([
                'invitation_id' => $invitation->id,
                'type' => 'organization_approved',
                'recipient_email' => $adminData->email,
                'content' => $message ?? 'Tu solicitud de organización ha sido aprobada.',
                'sent_at' => now()
            ]);

            DB::commit();

            // Generar password temporal
            $tempPassword = \Illuminate\Support\Str::random(12);
            $user->update(['password' => Hash::make($tempPassword)]);

            // Enviar email de aprobación
            $emailSent = $this->emailService->sendApprovalNotification(
                $invitation,
                $tempPassword,
                $message
            );

            Log::info('Solicitud de organización aprobada', [
                'invitation_id' => $invitation->id,
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'approved_by' => Auth::id(),
                'email_sent' => $emailSent
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Solicitud aprobada exitosamente',
                'organization' => $organization,
                'user' => $user
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Rechazar una solicitud de organización
     */
    private function rejectRequest(Invitation $invitation, ?string $message): JsonResponse
    {
        try {
            $invitation->update([
                'status_id' => 4, // rejected
                'processed_at' => now(),
                'processed_by' => Auth::id()
            ]);

            DB::commit();

            // Enviar email de rechazo
            $emailSent = $this->emailService->sendRejectionNotification($invitation, $message);

            Log::info('Solicitud de organización rechazada', [
                'invitation_id' => $invitation->id,
                'rejected_by' => Auth::id(),
                'message' => $message,
                'email_sent' => $emailSent
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Solicitud rechazada exitosamente'
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Solicitar correcciones para una organización
     */
    private function requestCorrections(Invitation $invitation, string $correctionsNotes): JsonResponse
    {
        try {
            $invitation->update([
                'status_id' => 5, // corrections_needed
                'processed_at' => now(),
                'processed_by' => Auth::id(),
                'corrections_notes' => $correctionsNotes
            ]);

            DB::commit();

            // Enviar email solicitando correcciones
            $emailSent = $this->emailService->sendCorrectionsRequest($invitation, $correctionsNotes);

            Log::info('Correcciones solicitadas para organización', [
                'invitation_id' => $invitation->id,
                'requested_by' => Auth::id(),
                'corrections_notes' => $correctionsNotes,
                'email_sent' => $emailSent
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Correcciones solicitadas exitosamente'
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
