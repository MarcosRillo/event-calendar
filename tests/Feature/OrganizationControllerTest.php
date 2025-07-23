<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Role;
use App\Models\TrustLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed basic data needed for tests
        Role::create(['name' => 'superadmin']);
        Role::create(['name' => 'organization_admin']);
        Role::create(['name' => 'organization_user']);
        
        TrustLevel::create(['name' => 'Basic']);
        TrustLevel::create(['name' => 'Premium']);
        TrustLevel::create(['name' => 'Enterprise']);
    }

    /** @test */
    public function super_admin_can_list_organizations()
    {
        $superAdminRole = Role::where('name', 'superadmin')->first();
        $superAdmin = User::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'superadmin@test.com',
            'password' => bcrypt('password'),
            'role_id' => $superAdminRole->id,
        ]);

        // Create test organizations
        $org1 = Organization::create([
            'name' => 'Organization 1',
            'slug' => 'organization-1',
            'email' => 'org1@test.com',
            'trust_level_id' => TrustLevel::first()->id,
        ]);

        $org2 = Organization::create([
            'name' => 'Organization 2',
            'slug' => 'organization-2',
            'email' => 'org2@test.com',
            'trust_level_id' => TrustLevel::first()->id,
        ]);

        Sanctum::actingAs($superAdmin);

        $response = $this->getJson('/api/super-admin/organizations-management');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'slug',
                            'email',
                            'is_active',
                            'trust_level',
                            'admin',
                            'created_at',
                            'updated_at'
                        ]
                    ],
                    'meta'
                ]);
    }

    /** @test */
    public function super_admin_can_view_organization()
    {
        $superAdminRole = Role::where('name', 'superadmin')->first();
        $superAdmin = User::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'superadmin@test.com',
            'password' => bcrypt('password'),
            'role_id' => $superAdminRole->id,
        ]);

        $organization = Organization::create([
            'name' => 'Test Organization',
            'slug' => 'test-organization',
            'email' => 'test@test.com',
            'trust_level_id' => TrustLevel::first()->id,
        ]);

        Sanctum::actingAs($superAdmin);

        $response = $this->getJson("/api/super-admin/organizations-management/{$organization->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'id',
                        'name',
                        'slug',
                        'email',
                        'is_active',
                        'trust_level',
                        'admin'
                    ]
                ]);
    }

    /** @test */
    public function super_admin_can_update_organization()
    {
        $superAdminRole = Role::where('name', 'superadmin')->first();
        $superAdmin = User::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'superadmin@test.com',
            'password' => bcrypt('password'),
            'role_id' => $superAdminRole->id,
        ]);

        $organization = Organization::create([
            'name' => 'Original Name',
            'slug' => 'original-name',
            'email' => 'original@test.com',
            'trust_level_id' => TrustLevel::first()->id,
        ]);

        Sanctum::actingAs($superAdmin);

        $updateData = [
            'name' => 'Updated Organization',
            'slug' => 'updated-organization',
            'email' => 'updated@test.com',
            'website_url' => 'https://updated.com',
            'address' => 'Updated Address',
            'phone' => '+1234567890',
            'trust_level_id' => TrustLevel::first()->id,
            'is_active' => true
        ];

        $response = $this->putJson("/api/super-admin/organizations-management/{$organization->id}", $updateData);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'id',
                        'name',
                        'slug',
                        'email',
                        'website_url',
                        'address',
                        'phone'
                    ]
                ]);

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'name' => 'Updated Organization',
            'slug' => 'updated-organization',
            'email' => 'updated@test.com'
        ]);
    }

    /** @test */
    public function super_admin_can_soft_delete_organization()
    {
        $superAdminRole = Role::where('name', 'superadmin')->first();
        $superAdmin = User::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'superadmin@test.com',
            'password' => bcrypt('password'),
            'role_id' => $superAdminRole->id,
        ]);

        $organization = Organization::create([
            'name' => 'Test Organization',
            'slug' => 'test-organization',
            'email' => 'test@test.com',
            'trust_level_id' => TrustLevel::first()->id,
        ]);

        Sanctum::actingAs($superAdmin);

        $response = $this->deleteJson("/api/super-admin/organizations-management/{$organization->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Organization deleted successfully. It can be restored later.'
                ]);

        $this->assertSoftDeleted('organizations', [
            'id' => $organization->id
        ]);
    }

    /** @test */
    public function super_admin_can_restore_organization()
    {
        $superAdminRole = Role::where('name', 'superadmin')->first();
        $superAdmin = User::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'superadmin@test.com',
            'password' => bcrypt('password'),
            'role_id' => $superAdminRole->id,
        ]);

        $organization = Organization::create([
            'name' => 'Test Organization',
            'slug' => 'test-organization',
            'email' => 'test@test.com',
            'trust_level_id' => TrustLevel::first()->id,
        ]);

        // Soft delete the organization first
        $organization->delete();

        Sanctum::actingAs($superAdmin);

        $response = $this->postJson("/api/super-admin/organizations-management/{$organization->id}/restore");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Organization restored successfully.'
                ]);

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'deleted_at' => null
        ]);
    }

    /** @test */
    public function super_admin_can_permanently_delete_organization()
    {
        $superAdminRole = Role::where('name', 'superadmin')->first();
        $superAdmin = User::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'superadmin@test.com',
            'password' => bcrypt('password'),
            'role_id' => $superAdminRole->id,
        ]);

        $organization = Organization::create([
            'name' => 'Test Organization',
            'slug' => 'test-organization',
            'email' => 'test@test.com',
            'trust_level_id' => TrustLevel::first()->id,
        ]);

        // Soft delete the organization first
        $organization->delete();

        Sanctum::actingAs($superAdmin);

        $response = $this->deleteJson("/api/super-admin/organizations-management/{$organization->id}/force");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Organization permanently deleted. This action cannot be undone.'
                ]);

        $this->assertDatabaseMissing('organizations', [
            'id' => $organization->id
        ]);
    }

    /** @test */
    public function super_admin_can_toggle_organization_status()
    {
        $superAdminRole = Role::where('name', 'superadmin')->first();
        $superAdmin = User::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'superadmin@test.com',
            'password' => bcrypt('password'),
            'role_id' => $superAdminRole->id,
        ]);

        $organization = Organization::create([
            'name' => 'Test Organization',
            'slug' => 'test-organization',
            'email' => 'test@test.com',
            'trust_level_id' => TrustLevel::first()->id,
            'is_active' => true,
        ]);

        Sanctum::actingAs($superAdmin);

        $response = $this->patchJson("/api/super-admin/organizations-management/{$organization->id}/toggle-status");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Organization deactivated successfully.'
                ]);

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'is_active' => false
        ]);
    }

    /** @test */
    public function regular_user_cannot_access_organization_management()
    {
        $regularRole = Role::where('name', 'organization_user')->first();
        $regularUser = User::create([
            'first_name' => 'Regular',
            'last_name' => 'User',
            'email' => 'regular@test.com',
            'password' => bcrypt('password'),
            'role_id' => $regularRole->id,
        ]);

        Sanctum::actingAs($regularUser);

        $response = $this->getJson('/api/super-admin/organizations-management');

        $response->assertStatus(403);
    }

    /** @test */
    public function update_organization_validates_required_fields()
    {
        $superAdminRole = Role::where('name', 'superadmin')->first();
        $superAdmin = User::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'superadmin@test.com',
            'password' => bcrypt('password'),
            'role_id' => $superAdminRole->id,
        ]);

        $organization = Organization::create([
            'name' => 'Test Organization',
            'slug' => 'test-organization',
            'email' => 'test@test.com',
            'trust_level_id' => TrustLevel::first()->id,
        ]);

        Sanctum::actingAs($superAdmin);

        $response = $this->putJson("/api/super-admin/organizations-management/{$organization->id}", [
            'name' => '', // Empty name should fail validation
            'email' => 'invalid-email' // Invalid email format
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['name', 'email']);
    }

    /** @test */
    public function soft_deleting_organization_also_soft_deletes_admin()
    {
        $superAdminRole = Role::where('name', 'superadmin')->first();
        $orgAdminRole = Role::where('name', 'organization_admin')->first();
        
        $superAdmin = User::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'superadmin@test.com',
            'password' => bcrypt('password'),
            'role_id' => $superAdminRole->id,
        ]);

        $orgAdmin = User::create([
            'first_name' => 'Org',
            'last_name' => 'Admin',
            'email' => 'orgadmin@test.com',
            'password' => bcrypt('password'),
            'role_id' => $orgAdminRole->id,
        ]);

        $organization = Organization::create([
            'name' => 'Test Organization',
            'slug' => 'test-organization',
            'email' => 'test@test.com',
            'trust_level_id' => TrustLevel::first()->id,
            'admin_id' => $orgAdmin->id,
        ]);

        $orgAdmin->update(['organization_id' => $organization->id]);

        Sanctum::actingAs($superAdmin);

        $response = $this->deleteJson("/api/super-admin/organizations-management/{$organization->id}");

        $response->assertStatus(200);

        // Check that both organization and admin are soft deleted
        $this->assertSoftDeleted('organizations', ['id' => $organization->id]);
        $this->assertSoftDeleted('users', ['id' => $orgAdmin->id]);
    }

    /** @test */
    public function toggling_organization_status_also_affects_admin()
    {
        $superAdminRole = Role::where('name', 'superadmin')->first();
        $orgAdminRole = Role::where('name', 'organization_admin')->first();
        
        $superAdmin = User::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'superadmin@test.com',
            'password' => bcrypt('password'),
            'role_id' => $superAdminRole->id,
        ]);

        $orgAdmin = User::create([
            'first_name' => 'Org',
            'last_name' => 'Admin',
            'email' => 'orgadmin@test.com',
            'password' => bcrypt('password'),
            'role_id' => $orgAdminRole->id,
            'is_active' => true,
        ]);

        $organization = Organization::create([
            'name' => 'Test Organization',
            'slug' => 'test-organization',
            'email' => 'test@test.com',
            'trust_level_id' => TrustLevel::first()->id,
            'admin_id' => $orgAdmin->id,
            'is_active' => true,
        ]);

        $orgAdmin->update(['organization_id' => $organization->id]);

        Sanctum::actingAs($superAdmin);

        $response = $this->patchJson("/api/super-admin/organizations-management/{$organization->id}/toggle-status");

        $response->assertStatus(200);

        // Check that both organization and admin are deactivated
        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'is_active' => false
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $orgAdmin->id,
            'is_active' => false
        ]);
    }

    /** @test */
    public function can_update_organization_admin()
    {
        $superAdminRole = Role::where('name', 'superadmin')->first();
        $orgAdminRole = Role::where('name', 'organization_admin')->first();
        
        $superAdmin = User::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'superadmin@test.com',
            'password' => bcrypt('password'),
            'role_id' => $superAdminRole->id,
        ]);

        $orgAdmin = User::create([
            'first_name' => 'Original',
            'last_name' => 'Admin',
            'email' => 'original@test.com',
            'password' => bcrypt('password'),
            'role_id' => $orgAdminRole->id,
        ]);

        $organization = Organization::create([
            'name' => 'Test Organization',
            'slug' => 'test-organization',
            'email' => 'test@test.com',
            'trust_level_id' => TrustLevel::first()->id,
            'admin_id' => $orgAdmin->id,
        ]);

        $orgAdmin->update(['organization_id' => $organization->id]);

        Sanctum::actingAs($superAdmin);

        $updateData = [
            'first_name' => 'Updated',
            'last_name' => 'Administrator',
            'email' => 'updated@test.com',
            'phone' => '+1234567890',
        ];

        $response = $this->putJson("/api/super-admin/organizations-management/{$organization->id}/admin", $updateData);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'admin',
                        'organization'
                    ]
                ]);

        $this->assertDatabaseHas('users', [
            'id' => $orgAdmin->id,
            'first_name' => 'Updated',
            'last_name' => 'Administrator',
            'email' => 'updated@test.com',
            'phone' => '+1234567890'
        ]);
    }

    /** @test */
    public function can_create_admin_for_organization_without_admin()
    {
        $superAdminRole = Role::where('name', 'superadmin')->first();
        
        $superAdmin = User::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'superadmin@test.com',
            'password' => bcrypt('password'),
            'role_id' => $superAdminRole->id,
        ]);

        $organization = Organization::create([
            'name' => 'Test Organization',
            'slug' => 'test-organization',
            'email' => 'test@test.com',
            'trust_level_id' => TrustLevel::first()->id,
            'admin_id' => null, // No admin initially
        ]);

        Sanctum::actingAs($superAdmin);

        $adminData = [
            'first_name' => 'New',
            'last_name' => 'Administrator',
            'email' => 'newadmin@test.com',
            'phone' => '+1234567890',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!'
        ];

        $response = $this->putJson("/api/super-admin/organizations-management/{$organization->id}/admin", $adminData);

        $response->assertStatus(200);

        // Check that admin was created and assigned
        $this->assertDatabaseHas('users', [
            'first_name' => 'New',
            'last_name' => 'Administrator',
            'email' => 'newadmin@test.com',
            'organization_id' => $organization->id
        ]);

        // Check that organization now has admin_id set
        $organization->refresh();
        $this->assertNotNull($organization->admin_id);
    }

    /** @test */
    public function restoring_organization_also_restores_admin()
    {
        $superAdminRole = Role::where('name', 'superadmin')->first();
        $orgAdminRole = Role::where('name', 'organization_admin')->first();
        
        $superAdmin = User::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'superadmin@test.com',
            'password' => bcrypt('password'),
            'role_id' => $superAdminRole->id,
        ]);

        $orgAdmin = User::create([
            'first_name' => 'Org',
            'last_name' => 'Admin',
            'email' => 'orgadmin@test.com',
            'password' => bcrypt('password'),
            'role_id' => $orgAdminRole->id,
        ]);

        $organization = Organization::create([
            'name' => 'Test Organization',
            'slug' => 'test-organization',
            'email' => 'test@test.com',
            'trust_level_id' => TrustLevel::first()->id,
            'admin_id' => $orgAdmin->id,
        ]);

        $orgAdmin->update(['organization_id' => $organization->id]);

        // First soft delete the organization and admin
        $organization->delete();
        $orgAdmin->delete();

        Sanctum::actingAs($superAdmin);

        $response = $this->postJson("/api/super-admin/organizations-management/{$organization->id}/restore");

        $response->assertStatus(200);

        // Check that both organization and admin are restored
        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'deleted_at' => null
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $orgAdmin->id,
            'deleted_at' => null,
            'is_active' => true
        ]);
    }
}
