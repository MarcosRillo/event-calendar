<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use App\Models\Role;
use App\Models\Invitation;
use App\Models\InvitationStatus;
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
        $superAdminRole = Role::factory()->create(['name' => 'super_admin']);
        $adminRole = Role::factory()->create(['name' => 'admin']);

        // Create users
        $this->superAdmin = User::factory()->create(['role_id' => $superAdminRole->id]);
        $this->regularUser = User::factory()->create(['role_id' => $adminRole->id]);

        // Create invitation statuses
        InvitationStatus::factory()->create(['name' => 'pending']);
        InvitationStatus::factory()->create(['name' => 'approved']);
        InvitationStatus::factory()->create(['name' => 'rejected']);

        Mail::fake();
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
        Invitation::factory()->create([
            'email' => 'duplicate@test.com',
            'status' => 'pending'
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
                        ->getJson('/api/organization-requests');

        // Assert
        $response->assertStatus(403);
    }

    public function test_super_admin_can_get_pending_requests()
    {
        // Arrange
        Invitation::factory()->count(3)->create(['status' => 'pending']);
        Invitation::factory()->count(2)->create(['status' => 'approved']);

        // Act
        $response = $this->actingAs($this->superAdmin)
                        ->getJson('/api/organization-requests');

        // Assert
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id',
                            'organization_name',
                            'contact_name',
                            'email',
                            'status',
                            'created_at'
                        ]
                    ],
                    'meta' => [
                        'current_page',
                        'total',
                        'per_page'
                    ]
                ]);

        // Should only return pending requests
        $this->assertEquals(3, $response->json('meta.total'));
    }

    public function test_super_admin_can_filter_requests_by_status()
    {
        // Arrange
        Invitation::factory()->count(2)->create(['status' => 'pending']);
        Invitation::factory()->count(3)->create(['status' => 'approved']);
        Invitation::factory()->count(1)->create(['status' => 'rejected']);

        // Act
        $response = $this->actingAs($this->superAdmin)
                        ->getJson('/api/organization-requests?status=approved');

        // Assert
        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('meta.total'));
    }

    public function test_super_admin_can_approve_request()
    {
        // Arrange
        $invitation = Invitation::factory()->create([
            'status' => 'pending',
            'organization_name' => 'Test Organization',
            'email' => 'test@org.com'
        ]);

        // Act
        $response = $this->actingAs($this->superAdmin)
                        ->patchJson("/api/organization-requests/{$invitation->id}", [
                            'status' => 'approved'
                        ]);

        // Assert
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'invitation',
                    'organization',
                    'admin_credentials'
                ]);

        $this->assertDatabaseHas('invitations', [
            'id' => $invitation->id,
            'status' => 'approved'
        ]);

        $this->assertDatabaseHas('organizations', [
            'name' => 'Test Organization',
            'email' => 'test@org.com'
        ]);
    }

    public function test_super_admin_can_reject_request()
    {
        // Arrange
        $invitation = Invitation::factory()->create([
            'status' => 'pending'
        ]);

        // Act
        $response = $this->actingAs($this->superAdmin)
                        ->patchJson("/api/organization-requests/{$invitation->id}", [
                            'status' => 'rejected',
                            'rejection_reason' => 'Insufficient information'
                        ]);

        // Assert
        $response->assertStatus(200)
                ->assertJson([
                    'message' => 'Solicitud rechazada correctamente'
                ]);

        $this->assertDatabaseHas('invitations', [
            'id' => $invitation->id,
            'status' => 'rejected',
            'rejection_reason' => 'Insufficient information'
        ]);
    }

    public function test_update_status_validates_required_fields()
    {
        // Arrange
        $invitation = Invitation::factory()->create(['status' => 'pending']);

        // Act
        $response = $this->actingAs($this->superAdmin)
                        ->patchJson("/api/organization-requests/{$invitation->id}", []);

        // Assert
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['status']);
    }

    public function test_update_status_validates_rejection_reason_when_rejecting()
    {
        // Arrange
        $invitation = Invitation::factory()->create(['status' => 'pending']);

        // Act
        $response = $this->actingAs($this->superAdmin)
                        ->patchJson("/api/organization-requests/{$invitation->id}", [
                            'status' => 'rejected'
                        ]);

        // Assert
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['rejection_reason']);
    }

    public function test_cannot_update_already_processed_request()
    {
        // Arrange
        $invitation = Invitation::factory()->create(['status' => 'approved']);

        // Act
        $response = $this->actingAs($this->superAdmin)
                        ->patchJson("/api/organization-requests/{$invitation->id}", [
                            'status' => 'rejected',
                            'rejection_reason' => 'Changed mind'
                        ]);

        // Assert
        $response->assertStatus(400)
                ->assertJson([
                    'error' => 'Esta solicitud ya ha sido procesada'
                ]);
    }

    public function test_regular_user_cannot_update_request_status()
    {
        // Arrange
        $invitation = Invitation::factory()->create(['status' => 'pending']);

        // Act
        $response = $this->actingAs($this->regularUser)
                        ->patchJson("/api/organization-requests/{$invitation->id}", [
                            'status' => 'approved'
                        ]);

        // Assert
        $response->assertStatus(403);
    }

    public function test_get_organization_request_statistics()
    {
        // Arrange
        Invitation::factory()->count(5)->create(['status' => 'pending']);
        Invitation::factory()->count(3)->create(['status' => 'approved']);
        Invitation::factory()->count(2)->create(['status' => 'rejected']);

        // Act
        $response = $this->actingAs($this->superAdmin)
                        ->getJson('/api/organization-requests/statistics');

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
            'organization_name' => 'Specific Organization',
            'status' => 'pending'
        ]);

        // Act
        $response = $this->actingAs($this->superAdmin)
                        ->getJson("/api/organization-requests/{$invitation->id}");

        // Assert
        $response->assertStatus(200)
                ->assertJson([
                    'id' => $invitation->id,
                    'organization_name' => 'Specific Organization',
                    'status' => 'pending'
                ]);
    }

    public function test_show_returns_404_for_nonexistent_invitation()
    {
        // Act
        $response = $this->actingAs($this->superAdmin)
                        ->getJson('/api/organization-requests/99999');

        // Assert
        $response->assertStatus(404);
    }
}
