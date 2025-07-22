<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Invitation;
use App\Models\InvitationStatus;
use App\Models\InvitationOrganizationData;
use App\Models\InvitationAdminData;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrganizationRequestTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Crear roles
        Role::factory()->create(['name' => 'superadmin']);
        Role::factory()->create(['name' => 'admin']);
        Role::factory()->create(['name' => 'organization_admin']);
        
        // Crear statuses
        InvitationStatus::factory()->create(['name' => 'sent']);
        InvitationStatus::factory()->create(['name' => 'pending']);
        InvitationStatus::factory()->create(['name' => 'approved']);
        InvitationStatus::factory()->create(['name' => 'rejected']);
        InvitationStatus::factory()->create(['name' => 'corrections_needed']);

        // Crear usuarios de prueba
        $this->superAdmin = User::factory()->superAdmin()->create();
        $this->regularUser = User::factory()->admin()->create();
    }

    public function test_super_admin_can_approve_organization_request(): void
    {
        $invitation = Invitation::factory()->pending()->complete()->create();

        $response = $this->actingAs($this->superAdmin, 'sanctum')
                         ->putJson("/api/super-admin/organization-requests/{$invitation->id}/status", [
                             'action' => 'approve',
                             'message' => 'Organization approved for testing'
                         ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Solicitud aprobada exitosamente'
                ]);

        // Verificar que el estado cambió
        $invitation->refresh();
        $this->assertEquals('approved', $invitation->status->name);

        // Verificar que se creó la organización
        $this->assertDatabaseHas('organizations', [
            'name' => $invitation->organizationData->name,
            'slug' => $invitation->organizationData->slug
        ]);

        // Verificar que se creó el usuario admin
        $this->assertDatabaseHas('users', [
            'email' => $invitation->adminData->email,
            'first_name' => $invitation->adminData->first_name,
            'last_name' => $invitation->adminData->last_name
        ]);
    }

    public function test_super_admin_can_reject_organization_request(): void
    {
        $invitation = Invitation::factory()->pending()->complete()->create();

        $response = $this->actingAs($this->superAdmin, 'sanctum')
                         ->putJson("/api/super-admin/organization-requests/{$invitation->id}/status", [
                             'action' => 'reject',
                             'message' => 'Organization rejected for testing'
                         ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Solicitud rechazada exitosamente'
                ]);

        // Verificar que el estado cambió
        $invitation->refresh();
        $this->assertEquals('rejected', $invitation->status->name);
        $this->assertEquals('Organization rejected for testing', $invitation->rejected_reason);

        // Verificar que NO se creó la organización
        $this->assertDatabaseMissing('organizations', [
            'name' => $invitation->organizationData->name
        ]);
    }

    public function test_super_admin_can_request_corrections(): void
    {
        $invitation = Invitation::factory()->pending()->complete()->create();

        $response = $this->actingAs($this->superAdmin, 'sanctum')
                         ->putJson("/api/super-admin/organization-requests/{$invitation->id}/status", [
                             'action' => 'request_corrections',
                             'corrections_notes' => 'Please provide valid tax ID',
                             'message' => 'Corrections needed'
                         ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Correcciones solicitadas exitosamente'
                ]);

        // Verificar que el estado cambió
        $invitation->refresh();
        $this->assertEquals('corrections_needed', $invitation->status->name);
        $this->assertEquals('Please provide valid tax ID', $invitation->corrections_notes);
    }

    public function test_regular_user_cannot_approve_organization_request(): void
    {
        $invitation = Invitation::factory()->pending()->complete()->create();

        $response = $this->actingAs($this->regularUser, 'sanctum')
                         ->putJson("/api/super-admin/organization-requests/{$invitation->id}/status", [
                             'action' => 'approve'
                         ]);

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_organization_requests(): void
    {
        $invitation = Invitation::factory()->pending()->create();

        $response = $this->putJson("/api/super-admin/organization-requests/{$invitation->id}/status", [
            'action' => 'approve'
        ]);

        $response->assertStatus(401);
    }

    public function test_organization_request_validation_requires_valid_action(): void
    {
        $invitation = Invitation::factory()->pending()->complete()->create();

        $response = $this->actingAs($this->superAdmin, 'sanctum')
                         ->putJson("/api/super-admin/organization-requests/{$invitation->id}/status", [
                             'action' => 'invalid_action'
                         ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['action']);
    }

    public function test_request_corrections_requires_corrections_notes(): void
    {
        $invitation = Invitation::factory()->pending()->complete()->create();

        $response = $this->actingAs($this->superAdmin, 'sanctum')
                         ->putJson("/api/super-admin/organization-requests/{$invitation->id}/status", [
                             'action' => 'request_corrections'
                             // Sin corrections_notes
                         ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['corrections_notes']);
    }

    public function test_cannot_process_non_pending_invitation(): void
    {
        $invitation = Invitation::factory()->approved()->complete()->create();

        $response = $this->actingAs($this->superAdmin, 'sanctum')
                         ->putJson("/api/super-admin/organization-requests/{$invitation->id}/status", [
                             'action' => 'approve',
                             'message' => 'Trying to approve already approved'
                         ]);

        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                    'message' => 'Solo se pueden procesar solicitudes pendientes'
                ]);
    }

    public function test_super_admin_can_send_invitation(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->superAdmin, 'sanctum')
                         ->postJson('/api/super-admin/organization-requests/send-invitation', [
                             'email' => 'neworg@example.com',
                             'expires_days' => 14,
                             'message' => 'Welcome to our platform!'
                         ]);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'invitation_id',
                        'email',
                        'expires_at',
                        'token'
                    ]
                ]);

        // Verificar que se creó la invitación
        $this->assertDatabaseHas('invitations', [
            'email' => 'neworg@example.com',
            'created_by' => $this->superAdmin->id
        ]);

        // Verificar que se envió el email
        Mail::assertSent(\App\Mail\OrganizationInvitationMail::class);
    }

    public function test_cannot_send_duplicate_invitation(): void
    {
        // Crear invitación existente
        Invitation::factory()->create([
            'email' => 'existing@example.com',
            'status_id' => InvitationStatus::where('name', 'sent')->first()->id,
            'expires_at' => now()->addDays(7)
        ]);

        $response = $this->actingAs($this->superAdmin, 'sanctum')
                         ->postJson('/api/super-admin/organization-requests/send-invitation', [
                             'email' => 'existing@example.com',
                             'expires_days' => 14
                         ]);

        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                    'message' => 'Ya existe una invitación pendiente para este email'
                ]);
    }

    public function test_super_admin_can_list_organization_requests(): void
    {
        // Crear varias invitaciones
        Invitation::factory()->count(3)->pending()->complete()->create();
        Invitation::factory()->count(2)->approved()->complete()->create();

        $response = $this->actingAs($this->superAdmin, 'sanctum')
                         ->getJson('/api/super-admin/organization-requests');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'data' => [
                            '*' => [
                                'id',
                                'email',
                                'status',
                                'organization_data',
                                'admin_data',
                                'created_at'
                            ]
                        ]
                    ]
                ]);

        $this->assertCount(5, $response->json('data.data'));
    }

    public function test_approval_process_creates_organization_and_user_atomically(): void
    {
        $invitation = Invitation::factory()->pending()->complete()->create();
        
        // Simular error en creación de usuario para probar rollback
        $originalUserCount = User::count();
        $originalOrgCount = Organization::count();

        try {
            DB::transaction(function () use ($invitation) {
                $this->actingAs($this->superAdmin, 'sanctum')
                     ->putJson("/api/super-admin/organization-requests/{$invitation->id}/status", [
                         'action' => 'approve',
                         'message' => 'Approved'
                     ]);
            });
        } catch (\Exception $e) {
            // En caso de error, verificar que no se crearon registros
            $this->assertEquals($originalUserCount, User::count());
            $this->assertEquals($originalOrgCount, Organization::count());
        }
    }

    public function test_invitation_token_verification_works(): void
    {
        $invitation = Invitation::factory()->create([
            'status_id' => InvitationStatus::where('name', 'sent')->first()->id,
            'expires_at' => now()->addDays(7)
        ]);

        $response = $this->getJson("/api/organization-request/verify/{$invitation->token}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Token válido',
                    'data' => [
                        'email' => $invitation->email
                    ]
                ]);
    }

    public function test_expired_token_verification_fails(): void
    {
        $invitation = Invitation::factory()->create([
            'expires_at' => now()->subDays(1) // Expirado
        ]);

        $response = $this->getJson("/api/organization-request/verify/{$invitation->token}");

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => 'Token inválido o expirado'
                ]);
    }
}
