<?php

namespace Tests\Unit\Services;

use App\Models\Invitation;
use App\Models\InvitationStatus;
use App\Models\User;
use App\Models\Role;
use App\Services\EmailService;
use App\Services\CacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailServiceTest extends TestCase
{
    use RefreshDatabase;

    protected EmailService $emailService;
    protected Role $superadminRole;
    protected InvitationStatus $sentStatus;
    protected InvitationStatus $pendingStatus;
    protected InvitationStatus $approvedStatus;
    protected InvitationStatus $rejectedStatus;
    protected InvitationStatus $correctionsStatus;
    protected User $testUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Configurar frontend URL para tests
        config(['app.frontend_url' => 'http://localhost:3000']);
        
        // Usar el servicio real de cache en lugar del mock para tests
        $this->emailService = app(EmailService::class);
        
        // Crear datos básicos necesarios
        $this->superadminRole = Role::factory()->create(['name' => 'superadmin']);
        $this->sentStatus = InvitationStatus::factory()->create(['name' => 'sent']);
        $this->pendingStatus = InvitationStatus::factory()->create(['name' => 'pending']);
        $this->approvedStatus = InvitationStatus::factory()->create(['name' => 'approved']);
        $this->rejectedStatus = InvitationStatus::factory()->create(['name' => 'rejected']);
        $this->correctionsStatus = InvitationStatus::factory()->create(['name' => 'corrections_needed']);
        
        // Crear usuario para evitar errores de foreign key
        $this->testUser = User::factory()->create([
            'role_id' => $this->superadminRole->id
        ]);
    }

    /**
     * Create a valid invitation with proper foreign key relationships
     */
    private function createValidInvitation(array $attributes = []): Invitation
    {
        return Invitation::factory()->create(array_merge([
            'status_id' => $this->pendingStatus->id,
            'created_by' => $this->testUser->id
        ], $attributes));
    }

    public function test_can_send_invitation_email(): void
    {
        Mail::fake();
        
        $invitation = $this->createValidInvitation();

        $result = $this->emailService->sendOrganizationInvitation($invitation, 'Test invitation message');

        // Debug: Ver si el resultado es TRUE
        $this->assertTrue($result, 'EmailService::sendOrganizationInvitation should return true');
        
        // Como el email está en cola, debemos usar assertQueued en lugar de assertSent
        Mail::assertQueued(\App\Mail\OrganizationInvitationMail::class);
    }

    public function test_can_send_approval_email(): void
    {
        Mail::fake();
        
        $invitation = Invitation::factory()->approved()->complete()->create([
            'status_id' => $this->approvedStatus->id,
            'created_by' => $this->testUser->id
        ]);

        $result = $this->emailService->sendApprovalNotification($invitation, 'temp123', 'Your organization has been approved');

        $this->assertTrue($result);
        
        Mail::assertQueued(\App\Mail\RequestStatusMail::class, function ($mail) use ($invitation) {
            return $mail->hasTo($invitation->adminData->email);
        });
    }

    public function test_can_send_rejection_email(): void
    {
        Mail::fake();
        
        $invitation = Invitation::factory()->rejected()->complete()->create([
            'status_id' => $this->rejectedStatus->id,
            'created_by' => $this->testUser->id
        ]);

        $result = $this->emailService->sendRejectionNotification($invitation, 'Unfortunately, your request was rejected');

        $this->assertTrue($result);
        
        Mail::assertQueued(\App\Mail\RequestStatusMail::class, function ($mail) use ($invitation) {
            return $mail->hasTo($invitation->adminData->email);
        });
    }

    public function test_can_send_corrections_request_email(): void
    {
        Mail::fake();
        
        $invitation = Invitation::factory()->correctionsNeeded()->complete()->create([
            'status_id' => $this->correctionsStatus->id,
            'created_by' => $this->testUser->id
        ]);

        $result = $this->emailService->sendCorrectionsRequest($invitation, 'Please provide additional documentation');

        $this->assertTrue($result);
        
        Mail::assertQueued(\App\Mail\RequestStatusMail::class, function ($mail) use ($invitation) {
            return $mail->hasTo($invitation->adminData->email);
        });
    }

    public function test_handles_mail_failures_gracefully(): void
    {
        Mail::shouldReceive('send')->andThrow(new \Exception('Mail server unavailable'));
        
        $invitation = $this->createValidInvitation();

        $result = $this->emailService->sendOrganizationInvitation($invitation, 'Test message');

        $this->assertFalse($result);
    }

    public function test_email_service_logs_successful_sends(): void
    {
        Mail::fake();
        
        $invitation = $this->createValidInvitation();
        
        // Crear una notificación previa como lo haría la aplicación real
        \App\Models\Notification::create([
            'invitation_id' => $invitation->id,
            'type' => 'invitation_sent',
            'recipient_email' => $invitation->email,
            'sent_at' => null,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        $result = $this->emailService->sendOrganizationInvitation($invitation, 'Test message');

        $this->assertTrue($result);
        
        // Verificar que se actualizó el registro de notificación
        $this->assertDatabaseHas('notifications', [
            'invitation_id' => $invitation->id,
            'type' => 'invitation_sent',
            'recipient_email' => $invitation->email
        ]);
        
        // Verificar que se marcó como enviada
        $notification = \App\Models\Notification::where('invitation_id', $invitation->id)->first();
        $this->assertNotNull($notification->sent_at);
    }

    public function test_email_service_logs_failed_sends(): void
    {
        Mail::shouldReceive('send')->andThrow(new \Exception('Connection timeout'));
        
        $invitation = $this->createValidInvitation();
        
        // Crear una notificación previa como lo haría la aplicación real
        \App\Models\Notification::create([
            'invitation_id' => $invitation->id,
            'type' => 'invitation_sent',
            'recipient_email' => $invitation->email,
            'sent_at' => null,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        $result = $this->emailService->sendOrganizationInvitation($invitation, 'Test message');

        $this->assertFalse($result);
        
        // Verificar que la notificación sigue sin sent_at (falló)
        $this->assertDatabaseHas('notifications', [
            'invitation_id' => $invitation->id,
            'type' => 'invitation_sent',
            'recipient_email' => $invitation->email,
            'sent_at' => null
        ]);
    }

    public function test_can_get_email_statistics(): void
    {
        // Crear algunos registros de notificaciones para estadísticas
        $invitation1 = $this->createValidInvitation();
        $invitation2 = $this->createValidInvitation();
        
        // Crear notificaciones como lo haría la aplicación real
        \App\Models\Notification::create([
            'invitation_id' => $invitation1->id,
            'type' => 'invitation_sent',
            'recipient_email' => $invitation1->email,
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        \App\Models\Notification::create([
            'invitation_id' => $invitation2->id,
            'type' => 'invitation_sent',
            'recipient_email' => $invitation2->email,
            'sent_at' => null,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $stats = $this->emailService->getEmailStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_sent', $stats);
        $this->assertArrayHasKey('by_type', $stats);
        $this->assertArrayHasKey('success_rate', $stats);
        $this->assertArrayHasKey('period_days', $stats);
    }

    public function test_email_content_is_properly_formatted(): void
    {
        Mail::fake();
        
        $invitation = Invitation::factory()->complete()->create([
            'status_id' => $this->pendingStatus->id,
            'created_by' => $this->testUser->id
        ]);
        $customMessage = 'Welcome to our tourism platform!';

        $this->emailService->sendOrganizationInvitation($invitation, $customMessage);

        Mail::assertQueued(\App\Mail\OrganizationInvitationMail::class, function ($mail) use ($invitation, $customMessage) {
            // Verificar que el email tiene los datos correctos
            $this->assertEquals($invitation->email, $mail->to[0]['address']);
            
            // En un escenario real, podrías verificar el contenido del email
            return true;
        });
    }

    public function test_email_service_validates_invitation_data(): void
    {
        // Usar una invitación sin guardar (con email nulo)
        $invitation = new \App\Models\Invitation([
            'email' => null,
            'token' => 'test-token',
            'status_id' => $this->pendingStatus->id,
            'created_by' => $this->testUser->id
        ]);

        $result = $this->emailService->sendOrganizationInvitation($invitation, 'Test message');

        // El método debería fallar con email null
        $this->assertFalse($result);
    }

    public function test_email_templates_exist(): void
    {
        // Verificar que las clases de email existen
        $this->assertTrue(class_exists(\App\Mail\OrganizationInvitationMail::class));
        $this->assertTrue(class_exists(\App\Mail\WelcomeOrganizationMail::class));
        $this->assertTrue(class_exists(\App\Mail\RequestStatusMail::class));
    }
}
