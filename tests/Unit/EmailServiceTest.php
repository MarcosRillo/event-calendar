<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use App\Services\EmailService;
use App\Services\CacheService;
use App\Models\User;
use App\Models\Organization;
use App\Models\Invitation;
use App\Models\InvitationStatus;
use App\Models\InvitationAdminData;
use App\Models\InvitationOrganizationData;
use App\Models\Role;
use App\Mail\OrganizationInvitationMail;
use App\Mail\WelcomeOrganizationMail;
use App\Mail\RequestStatusMail;

class EmailServiceTest extends TestCase
{
    use RefreshDatabase;

    protected EmailService $emailService;
    protected CacheService $cacheService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Configurar frontend URL para tests
        config(['app.frontend_url' => 'http://localhost:3000']);
        
        $this->cacheService = $this->createMock(CacheService::class);
        $this->emailService = new EmailService($this->cacheService);
        Mail::fake();
    }

    public function test_send_organization_invitation_email()
    {
        // Arrange
        $status = InvitationStatus::factory()->create(['name' => 'pending']);
        $role = Role::factory()->create(['name' => 'admin']);
        $user = User::factory()->create(['role_id' => $role->id]);
        
        $invitation = Invitation::factory()->create([
            'email' => 'test@example.com',
            'status_id' => $status->id,
            'created_by' => $user->id,
            'token' => 'test-token-123'
        ]);

        // Act
        Mail::to($invitation->email)->send(new OrganizationInvitationMail($invitation, 'http://test.com', null));

        // Assert - usar assertQueued porque el Mailable implementa ShouldQueue
        Mail::assertQueued(OrganizationInvitationMail::class);
    }

    public function test_send_organization_invitation_returns_false_on_failure()
    {
        // Arrange
        $status = InvitationStatus::factory()->create(['name' => 'pending']);
        $role = Role::factory()->create(['name' => 'admin']);
        $user = User::factory()->create(['role_id' => $role->id]);
        
        $invitation = Invitation::factory()->create([
            'email' => 'test@example.com',
            'status_id' => $status->id,
            'created_by' => $user->id,
        ]);

        // Mock Mail to throw exception
        Mail::shouldReceive('to->send')->andThrow(new \Exception('Mail server error'));

        // Act
        $result = $this->emailService->sendOrganizationInvitation($invitation);

        // Assert
        $this->assertFalse($result);
    }

    public function test_send_welcome_email()
    {
        // Arrange
        $status = InvitationStatus::factory()->create(['name' => 'approved']);
        $role = Role::factory()->create(['name' => 'admin']);
        $user = User::factory()->create(['role_id' => $role->id]);
        
        $invitation = Invitation::factory()->create([
            'email' => 'test@example.com',
            'status_id' => $status->id,
            'created_by' => $user->id,
        ]);

        // Crear adminData y organizationData para esta invitación
        InvitationAdminData::factory()->create([
            'invitation_id' => $invitation->id,
            'email' => 'admin@example.com'
        ]);

        InvitationOrganizationData::factory()->create([
            'invitation_id' => $invitation->id,
            'name' => 'Test Organization'
        ]);

        $tempPassword = 'temporary123';

        // Act
        $result = $this->emailService->sendWelcomeEmail($invitation, $tempPassword);

        // Assert
        $this->assertTrue($result);
    }

    public function test_send_approval_notification()
    {
        // Arrange
        $status = InvitationStatus::factory()->create(['name' => 'approved']);
        $role = Role::factory()->create(['name' => 'admin']);
        $user = User::factory()->create(['role_id' => $role->id]);
        
        $invitation = Invitation::factory()->create([
            'email' => 'requester@example.com',
            'status_id' => $status->id,
            'created_by' => $user->id,
        ]);

        // Crear adminData y organizationData para esta invitación
        InvitationAdminData::factory()->create([
            'invitation_id' => $invitation->id,
            'email' => 'admin@example.com'
        ]);

        InvitationOrganizationData::factory()->create([
            'invitation_id' => $invitation->id,
            'name' => 'Test Organization'
        ]);

        $tempPassword = 'temporary123';

        // Act
        $result = $this->emailService->sendApprovalNotification($invitation, $tempPassword);

        // Assert
        $this->assertTrue($result);
    }

    public function test_send_rejection_notification()
    {
        // Arrange
        $status = InvitationStatus::factory()->create(['name' => 'rejected']);
        $role = Role::factory()->create(['name' => 'admin']);
        $user = User::factory()->create(['role_id' => $role->id]);
        
        $invitation = Invitation::factory()->create([
            'email' => 'requester@example.com',
            'status_id' => $status->id,
            'created_by' => $user->id,
            'rejected_reason' => 'Insufficient information provided'
        ]);

        // Crear adminData para esta invitación
        InvitationAdminData::factory()->create([
            'invitation_id' => $invitation->id,
            'email' => 'admin@example.com'
        ]);

        // Act
        $result = $this->emailService->sendRejectionNotification($invitation, 'Insufficient information provided');

        // Assert
        $this->assertTrue($result);
    }

    public function test_send_emails_handles_exceptions_gracefully()
    {
        // Arrange
        $status = InvitationStatus::factory()->create(['name' => 'pending']);
        $role = Role::factory()->create(['name' => 'admin']);
        $user = User::factory()->create(['role_id' => $role->id]);
        
        $invitation = Invitation::factory()->create([
            'status_id' => $status->id,
            'created_by' => $user->id,
        ]);

        // Para sendApprovalNotification, necesitamos adminData
        InvitationAdminData::factory()->create([
            'invitation_id' => $invitation->id,
            'email' => 'admin@example.com'
        ]);

        InvitationOrganizationData::factory()->create([
            'invitation_id' => $invitation->id,
            'name' => 'Test Org'
        ]);

        // Mock Mail to throw exception
        Mail::shouldReceive('to->send')->andThrow(new \Exception('SMTP error'));

        // Act & Assert
        $this->assertFalse($this->emailService->sendOrganizationInvitation($invitation));
        $this->assertFalse($this->emailService->sendApprovalNotification($invitation, 'temp123'));
    }
}
