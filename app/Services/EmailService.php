<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\Invitation;
use App\Models\Notification;
use App\Mail\OrganizationInvitationMail;
use App\Mail\RequestStatusMail;
use App\Mail\WelcomeOrganizationMail;
use Exception;

class EmailService
{
    /**
     * Enviar invitación para crear organización
     * 
     * @param Invitation $invitation
     * @param string|null $customMessage
     * @return bool
     */
    public function sendOrganizationInvitation(Invitation $invitation, ?string $customMessage = null): bool
    {
        try {
            $invitationUrl = $this->generateInvitationUrl($invitation->token);
            
            Mail::to($invitation->email)->send(
                new OrganizationInvitationMail($invitation, $invitationUrl, $customMessage)
            );

            // Actualizar notificación como enviada
            $this->updateNotificationAsSent($invitation, 'invitation_sent');

            Log::info('Email de invitación enviado', [
                'invitation_id' => $invitation->id,
                'email' => $invitation->email
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Error al enviar email de invitación', [
                'invitation_id' => $invitation->id,
                'email' => $invitation->email,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Enviar notificación de solicitud aprobada
     * 
     * @param Invitation $invitation
     * @param string $tempPassword
     * @param string|null $customMessage
     * @return bool
     */
    public function sendApprovalNotification(Invitation $invitation, string $tempPassword, ?string $customMessage = null): bool
    {
        try {
            $loginUrl = config('app.frontend_url') . '/login';
            
            Mail::to($invitation->adminData->email)->send(
                new RequestStatusMail(
                    $invitation,
                    'approved',
                    $customMessage ?? 'Tu solicitud de organización ha sido aprobada.',
                    [
                        'login_url' => $loginUrl,
                        'temp_password' => $tempPassword,
                        'organization_name' => $invitation->organizationData->name
                    ]
                )
            );

            $this->updateNotificationAsSent($invitation, 'request_approved');

            Log::info('Email de aprobación enviado', [
                'invitation_id' => $invitation->id,
                'email' => $invitation->adminData->email
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Error al enviar email de aprobación', [
                'invitation_id' => $invitation->id,
                'email' => $invitation->adminData->email,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Enviar notificación de solicitud rechazada
     * 
     * @param Invitation $invitation
     * @param string|null $reason
     * @return bool
     */
    public function sendRejectionNotification(Invitation $invitation, ?string $reason = null): bool
    {
        try {
            Mail::to($invitation->adminData->email)->send(
                new RequestStatusMail(
                    $invitation,
                    'rejected',
                    $reason ?? 'Tu solicitud de organización ha sido rechazada.',
                    ['reason' => $reason]
                )
            );

            $this->updateNotificationAsSent($invitation, 'request_rejected');

            Log::info('Email de rechazo enviado', [
                'invitation_id' => $invitation->id,
                'email' => $invitation->adminData->email
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Error al enviar email de rechazo', [
                'invitation_id' => $invitation->id,
                'email' => $invitation->adminData->email,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Enviar solicitud de correcciones
     * 
     * @param Invitation $invitation
     * @param string $corrections
     * @param string|null $customMessage
     * @return bool
     */
    public function sendCorrectionsRequest(Invitation $invitation, string $corrections, ?string $customMessage = null): bool
    {
        try {
            $correctionUrl = $this->generateInvitationUrl($invitation->token);
            
            Mail::to($invitation->adminData->email)->send(
                new RequestStatusMail(
                    $invitation,
                    'corrections_needed',
                    $customMessage ?? 'Se requieren correcciones en tu solicitud de organización.',
                    [
                        'corrections' => $corrections,
                        'correction_url' => $correctionUrl
                    ]
                )
            );

            $this->updateNotificationAsSent($invitation, 'corrections_requested');

            Log::info('Email de correcciones enviado', [
                'invitation_id' => $invitation->id,
                'email' => $invitation->adminData->email
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Error al enviar email de correcciones', [
                'invitation_id' => $invitation->id,
                'email' => $invitation->adminData->email,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Enviar email de bienvenida después de crear organización
     * 
     * @param Invitation $invitation
     * @param string $tempPassword
     * @return bool
     */
    public function sendWelcomeEmail(Invitation $invitation, string $tempPassword): bool
    {
        try {
            Mail::to($invitation->adminData->email)->send(
                new WelcomeOrganizationMail($invitation, $tempPassword)
            );

            Log::info('Email de bienvenida enviado', [
                'invitation_id' => $invitation->id,
                'email' => $invitation->adminData->email,
                'organization' => $invitation->organizationData->name
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Error al enviar email de bienvenida', [
                'invitation_id' => $invitation->id,
                'email' => $invitation->adminData->email,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Enviar recordatorio de invitación próxima a expirar
     * 
     * @param Invitation $invitation
     * @return bool
     */
    public function sendExpirationReminder(Invitation $invitation): bool
    {
        try {
            $invitationUrl = $this->generateInvitationUrl($invitation->token);
            $daysLeft = now()->diffInDays($invitation->expires_at);
            
            Mail::to($invitation->email)->send(
                new RequestStatusMail(
                    $invitation,
                    'expiration_reminder',
                    "Tu invitación para crear una organización expira en {$daysLeft} días.",
                    [
                        'invitation_url' => $invitationUrl,
                        'days_left' => $daysLeft,
                        'expires_at' => $invitation->expires_at->format('d/m/Y H:i')
                    ]
                )
            );

            Log::info('Recordatorio de expiración enviado', [
                'invitation_id' => $invitation->id,
                'email' => $invitation->email,
                'days_left' => $daysLeft
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Error al enviar recordatorio de expiración', [
                'invitation_id' => $invitation->id,
                'email' => $invitation->email,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Generar URL de invitación
     * 
     * @param string $token
     * @return string
     */
    private function generateInvitationUrl(string $token): string
    {
        return config('app.frontend_url') . '/organization-request/' . $token;
    }

    /**
     * Actualizar notificación como enviada
     * 
     * @param Invitation $invitation
     * @param string $type
     * @return void
     */
    private function updateNotificationAsSent(Invitation $invitation, string $type): void
    {
        Notification::where('invitation_id', $invitation->id)
            ->where('type', $type)
            ->update(['sent_at' => now()]);
    }

    /**
     * Obtener estadísticas de emails enviados
     * 
     * @param int|null $days
     * @return array
     */
    public function getEmailStats(?int $days = 30): array
    {
        $since = now()->subDays($days ?? 30);
        
        $stats = [
            'total_sent' => Notification::where('sent_at', '>=', $since)->count(),
            'by_type' => Notification::where('sent_at', '>=', $since)
                ->groupBy('type')
                ->selectRaw('type, count(*) as count')
                ->pluck('count', 'type')
                ->toArray(),
            'success_rate' => $this->calculateSuccessRate($since),
            'period_days' => $days ?? 30
        ];

        return $stats;
    }

    /**
     * Calcular tasa de éxito de emails
     * 
     * @param \Carbon\Carbon $since
     * @return float
     */
    private function calculateSuccessRate($since): float
    {
        $total = Notification::where('created_at', '>=', $since)->count();
        $sent = Notification::where('sent_at', '>=', $since)->count();
        
        return $total > 0 ? round(($sent / $total) * 100, 2) : 0;
    }
}
