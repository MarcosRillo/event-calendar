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
use App\Models\Role;
use App\Models\InvitationStatus;

class EmailServiceBasicTest extends TestCase
{
    use RefreshDatabase;

    private EmailService $emailService;
    private CacheService $cacheService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheService = $this->createMock(CacheService::class);
        $this->emailService = new EmailService($this->cacheService);
        Mail::fake();
    }

    public function test_email_service_can_be_instantiated()
    {
        // Arrange & Act
        $cacheService = $this->createMock(CacheService::class);
        $emailService = new EmailService($cacheService);

        // Assert
        $this->assertInstanceOf(EmailService::class, $emailService);
    }

    public function test_send_organization_invitation_method_exists()
    {
        // Act
        $result = method_exists($this->emailService, 'sendOrganizationInvitation');

        // Assert
        $this->assertTrue($result);
    }

    public function test_send_welcome_email_method_exists()
    {
        // Act
        $result = method_exists($this->emailService, 'sendWelcomeEmail');

        // Assert
        $this->assertTrue($result);
    }

    public function test_send_approval_notification_method_exists()
    {
        // Act
        $result = method_exists($this->emailService, 'sendApprovalNotification');

        // Assert
        $this->assertTrue($result);
    }

    public function test_send_rejection_notification_method_exists()
    {
        // Act
        $result = method_exists($this->emailService, 'sendRejectionNotification');

        // Assert
        $this->assertTrue($result);
    }

    public function test_get_email_stats_method_exists()
    {
        // Act
        $result = method_exists($this->emailService, 'getEmailStats');

        // Assert
        $this->assertTrue($result);
    }

    private function createMockInvitation(): Invitation
    {
        $status = InvitationStatus::factory()->create(['name' => 'pending']);
        $role = Role::factory()->create(['name' => 'admin']);
        $user = User::factory()->create(['role_id' => $role->id]);
        
        return Invitation::factory()->create([
            'email' => 'test@example.com',
            'status_id' => $status->id,
            'created_by' => $user->id,
        ]);
    }

    private function createMockOrganization(): Organization
    {
        return Organization::factory()->create([
            'name' => 'Test Organization',
            'email' => 'org@example.com'
        ]);
    }

    private function createMockUser(): User
    {
        $role = Role::factory()->create(['name' => 'admin']);
        return User::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'user@example.com',
            'role_id' => $role->id,
        ]);
    }
}
