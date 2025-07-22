<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use App\Models\Role;
use App\Models\Invitation;
use App\Models\InvitationStatus;
use App\Models\InvitationOrganizationData;
use App\Models\InvitationAdminData;
use App\Models\Organization;
use App\Services\EmailService;

class OrganizationRequestControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create roles
        $superAdminRole = Role::factory()->create(['name' => 'superadmin']);
        $adminRole = Role::factory()->create(['name' => 'admin']);

        // Create users
        $this->superAdmin = User::factory()->create(['role_id' => $superAdminRole->id]);
        $this->regularUser = User::factory()->create(['role_id' => $adminRole->id]);

        // Create invitation statuses
        InvitationStatus::factory()->create(['name' => 'pending']);
        InvitationStatus::factory()->create(['name' => 'approved']);
        InvitationStatus::factory()->create(['name' => 'rejected']);
        InvitationStatus::factory()->create(['name' => 'corrections_needed']);

        Mail::fake();
    }

    private function getStatusId(string $statusName): int
    {
        return InvitationStatus::where('name', $statusName)->first()->id;
    }

    public function test_submit_request_creates_invitation_successfully()
    {
        // Arrange
        $requestData = [
            'organization_name' => 'Test Organization',
            'contact_name' => 'John Doe',
            'email' => 'john@testorg.com',
            'phone' => '+1234567890',
            'description' => 'We are a test organization',
            'website' => 'https://testorg.com',
            'address' => '123 Test Street',
            'message' => 'Please approve our request'
        ];

        // Act
        $response = $this->postJson('/api/organization-requests', $requestData);

        // Assert
        $response->assertStatus(201)
                ->assertJsonStructure([
                    'message',
                    'invitation' => [
                        'id',
                        'organization_name',
                        'contact_name',
                        'email',
                        'status'
                    ]
                ]);

        $this->assertDatabaseHas('invitations', [
            'email' => 'john@testorg.com',
        ]);

        $this->assertDatabaseHas('invitation_organization_data', [
            'name' => 'Test Organization',
            'email' => 'john@testorg.com',
        ]);

        $this->assertDatabaseHas('invitation_admin_data', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@testorg.com',
        ]);
    }

    public function test_submit_request_validates_required_fields()
    {
        // Act
        $response = $this->postJson('/api/organization-requests', []);

        // Assert
        $response->assertStatus(422)
                ->assertJsonValidationErrors([
                    'organization_name',
                    'contact_name',
                    'email'
                ]);
    }

    public function test_submit_request_validates_email_format()
    {
        // Arrange
        $requestData = [
            'organization_name' => 'Test Organization',
            'contact_name' => 'John Doe',
            'email' => 'invalid-email'
        ];

        // Act
        $response = $this->postJson('/api/organization-requests', $requestData);

        // Assert
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
    }

    public function test_submit_request_prevents_duplicate_emails()
    {
        // Arrange
        $pendingStatus = InvitationStatus::where('name', 'pending')->first();
        Invitation::factory()->create([
            'email' => 'duplicate@test.com',
            'status_id' => $pendingStatus->id
        ]);

        $requestData = [
            'organization_name' => 'Another Organization',
            'contact_name' => 'Jane Doe',
            'email' => 'duplicate@test.com'
        ];

        // Act
        $response = $this->postJson('/api/organization-requests', $requestData);

        // Assert
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
    }

    public function test_get_pending_requests_requires_super_admin()
    {
        // Act as regular user
        $response = $this->actingAs($this->regularUser)
                        ->getJson('/api/super-admin/organization-requests');

        // Assert
        $response->assertStatus(403);
    }

    public function test_super_admin_can_get_pending_requests()
    {
        // Arrange
        Invitation::factory()->count(3)->create(['status_id' => $this->getStatusId('pending')]);
        Invitation::factory()->count(2)->create(['status_id' => $this->getStatusId('approved')]);

        // Act
        $response = $this->actingAs($this->superAdmin)
                        ->getJson('/api/super-admin/organization-requests?status=pending');

        // Assert
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'current_page',
                        'data' => [
                            '*' => [
                                'id',
                                'email',
                                'token',
                                'status_id',
                                'expires_at',
                                'created_at',
                                'updated_at',
                                'status' => [
                                    'id',
                                    'name',
                                    'created_at',
                                    'updated_at'
                                ],
                                'created_by' => [
                                    'id',
                                    'first_name',
                                    'last_name',
                                    'email'
                                ]
                            ]
                        ],
                        'total',
                        'per_page'
                    ]
                ]);

        // Should return only pending requests
        $this->assertEquals(3, $response->json('data.total'));
    }

    public function test_super_admin_can_filter_requests_by_status()
    {
        // Arrange
        Invitation::factory()->count(2)->create(['status_id' => $this->getStatusId('pending')]);
        Invitation::factory()->count(3)->create(['status_id' => $this->getStatusId('approved')]);
        Invitation::factory()->count(1)->create(['status_id' => $this->getStatusId('rejected')]);

        // Act
        $response = $this->actingAs($this->superAdmin)
                        ->getJson('/api/super-admin/organization-requests?status=approved');

        // Assert
        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('data.total'));
    }

    public function test_super_admin_can_approve_request()
    {
        // Arrange - Create invitation with required related data
        $invitation = Invitation::factory()->create([
            'status_id' => $this->getStatusId('pending'),
            'email' => 'test@org.com'
        ]);

        // Create organization data
        InvitationOrganizationData::create([
            'invitation_id' => $invitation->id,
            'name' => 'Test Organization',
            'slug' => 'test-organization',
            'email' => 'admin@testorg.com',
            'phone' => '+1234567890',
            'website_url' => 'https://testorg.com',
            'address' => '123 Test Street',
            'description' => 'Test organization description'
        ]);

        // Create admin data
        InvitationAdminData::create([
            'invitation_id' => $invitation->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@testorg.com',
            'phone' => '+1234567890'
        ]);

        // Act
        $response = $this->actingAs($this->superAdmin)
                        ->patchJson("/api/super-admin/organization-requests/{$invitation->id}/status", [
                            'action' => 'approve'
                        ]);

        // Assert
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'invitation_id',
                        'action',
                        'processed_at'
                    ]
                ]);

        $this->assertDatabaseHas('invitations', [
            'id' => $invitation->id,
            'status_id' => $this->getStatusId('approved')
        ]);

        // Verify organization was created
        $this->assertDatabaseHas('organizations', [
            'name' => 'Test Organization',
            'email' => 'admin@testorg.com'
        ]);

        // Verify admin user was created
        $this->assertDatabaseHas('users', [
            'email' => 'john.doe@testorg.com',
            'first_name' => 'John',
            'last_name' => 'Doe'
        ]);
    }

    public function test_super_admin_can_reject_request()
    {
        // Arrange - Create invitation with required related data
        $invitation = Invitation::factory()->create([
            'status_id' => $this->getStatusId('pending')
        ]);

        // Create organization data (needed for rejection notification)
        InvitationOrganizationData::create([
            'invitation_id' => $invitation->id,
            'name' => 'Test Organization to Reject',
            'slug' => 'test-organization-reject',
            'email' => 'admin@rejecttest.com',
            'phone' => '+1234567890',
            'website_url' => 'https://rejecttest.com',
            'address' => '123 Test Street',
            'description' => 'Test organization for rejection'
        ]);

        // Create admin data (needed for rejection notification)
        InvitationAdminData::create([
            'invitation_id' => $invitation->id,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane.smith@rejecttest.com',
            'phone' => '+1234567890'
        ]);

        // Act
        $response = $this->actingAs($this->superAdmin)
                        ->patchJson("/api/super-admin/organization-requests/{$invitation->id}/status", [
                            'action' => 'reject',
                            'message' => 'Insufficient information'
                        ]);

        // Assert
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'invitation_id',
                        'action',
                        'processed_at'
                    ]
                ]);

        $this->assertDatabaseHas('invitations', [
            'id' => $invitation->id,
            'status_id' => $this->getStatusId('rejected'),
            'rejected_reason' => 'Insufficient information'
        ]);
    }

    public function test_update_status_validates_required_fields()
    {
        // Arrange
        $invitation = Invitation::factory()->create(['status_id' => $this->getStatusId('pending')]);
        
        // Crear datos de organización y admin requeridos
        InvitationOrganizationData::factory()->create(['invitation_id' => $invitation->id]);
        InvitationAdminData::factory()->create(['invitation_id' => $invitation->id]);

        // Act
        $response = $this->actingAs($this->superAdmin)
                        ->patchJson("/api/super-admin/organization-requests/{$invitation->id}/status", []);

        // Assert
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['action']);
    }

    public function test_update_status_validates_rejection_reason_when_rejecting()
    {
        // Arrange
        $invitation = Invitation::factory()->create(['status_id' => $this->getStatusId('pending')]);
        
        // Crear datos de organización y admin requeridos
        InvitationOrganizationData::factory()->create(['invitation_id' => $invitation->id]);
        InvitationAdminData::factory()->create(['invitation_id' => $invitation->id]);

        // Act
        $response = $this->actingAs($this->superAdmin)
                        ->patchJson("/api/super-admin/organization-requests/{$invitation->id}/status", [
                            'action' => 'request_corrections'
                        ]);

        // Assert
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['corrections_notes']);
    }

    public function test_cannot_update_already_processed_request()
    {
        // Arrange
        $invitation = Invitation::factory()->create(['status_id' => $this->getStatusId('approved')]);

        // Act
        $response = $this->actingAs($this->superAdmin)
                        ->patchJson("/api/super-admin/organization-requests/{$invitation->id}/status", [
                            'action' => 'reject',
                            'message' => 'Changed mind'
                        ]);

        // Assert
        $response->assertStatus(422)
                ->assertJson([
                    'error' => 'INVALID_STATUS'
                ]);
    }

    public function test_can_update_corrections_needed_request()
    {
        // Arrange
        $invitation = Invitation::factory()->create(['status_id' => $this->getStatusId('corrections_needed')]);
        
        // Create required related data
        InvitationOrganizationData::factory()->create(['invitation_id' => $invitation->id]);
        InvitationAdminData::factory()->create(['invitation_id' => $invitation->id]);

        // Act - Test approval
        $response = $this->actingAs($this->superAdmin)
                        ->patchJson("/api/super-admin/organization-requests/{$invitation->id}/status", [
                            'action' => 'approve'
                        ]);

        // Assert
        $response->assertStatus(200);
        $this->assertDatabaseHas('invitations', [
            'id' => $invitation->id,
            'status_id' => $this->getStatusId('approved')
        ]);
    }

    public function test_can_reject_corrections_needed_request()
    {
        // Arrange
        $invitation = Invitation::factory()->create(['status_id' => $this->getStatusId('corrections_needed')]);
        
        // Create required related data
        InvitationOrganizationData::factory()->create(['invitation_id' => $invitation->id]);
        InvitationAdminData::factory()->create(['invitation_id' => $invitation->id]);

        // Act - Test rejection
        $response = $this->actingAs($this->superAdmin)
                        ->patchJson("/api/super-admin/organization-requests/{$invitation->id}/status", [
                            'action' => 'reject',
                            'message' => 'Final rejection after corrections'
                        ]);

        // Assert
        $response->assertStatus(200);
        $this->assertDatabaseHas('invitations', [
            'id' => $invitation->id,
            'status_id' => $this->getStatusId('rejected')
        ]);
    }

    public function test_regular_user_cannot_update_request_status()
    {
        // Arrange
        $invitation = Invitation::factory()->create(['status_id' => $this->getStatusId('pending')]);

        // Act
        $response = $this->actingAs($this->regularUser)
                        ->patchJson("/api/super-admin/organization-requests/{$invitation->id}/status", [
                            'action' => 'approve'
                        ]);

        // Assert
        $response->assertStatus(403);
    }

    public function test_get_organization_request_statistics()
    {
        // Arrange
        Invitation::factory()->count(5)->create(['status_id' => $this->getStatusId('pending')]);
        Invitation::factory()->count(3)->create(['status_id' => $this->getStatusId('approved')]);
        Invitation::factory()->count(2)->create(['status_id' => $this->getStatusId('rejected')]);

        // Act
        $response = $this->actingAs($this->superAdmin)
                        ->getJson('/api/super-admin/organization-requests/statistics');

        // Assert
        $response->assertStatus(200)
                ->assertJson([
                    'total' => 10,
                    'pending' => 5,
                    'approved' => 3,
                    'rejected' => 2
                ]);
    }

    public function test_show_specific_invitation()
    {
        // Arrange
        $invitation = Invitation::factory()->create([
            'status_id' => $this->getStatusId('pending')
        ]);

        // Act
        $response = $this->actingAs($this->superAdmin)
                        ->getJson("/api/super-admin/organization-requests/{$invitation->id}");

        // Assert
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'id' => $invitation->id,
                        'status_id' => $this->getStatusId('pending')
                    ]
                ]);
    }

    public function test_show_returns_404_for_nonexistent_invitation()
    {
        // Act
        $response = $this->actingAs($this->superAdmin)
                        ->getJson('/api/super-admin/organization-requests/99999');

        // Assert
        $response->assertStatus(404);
    }
}
