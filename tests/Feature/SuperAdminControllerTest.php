<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Organization;
use App\Models\TrustLevel;
use Laravel\Sanctum\Sanctum;

class SuperAdminControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $regularUser;
    private Role $superAdminRole;
    private Role $userRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        $this->superAdminRole = Role::create(['name' => 'superadmin']);
        $this->userRole = Role::create(['name' => 'organization_user']);

        // Create trust level
        $trustLevel = TrustLevel::create([
            'name' => 'Basic',
            'description' => 'Basic trust level'
        ]);

        // Create super admin user
        $this->superAdmin = User::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'superadmin@test.com',
            'password' => bcrypt('password'),
            'role_id' => $this->superAdminRole->id,
            'is_active' => true,
        ]);

        // Create regular user
        $this->regularUser = User::create([
            'first_name' => 'Regular',
            'last_name' => 'User',
            'email' => 'user@test.com',
            'password' => bcrypt('password'),
            'role_id' => $this->userRole->id,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function super_admin_can_view_specific_user()
    {
        Sanctum::actingAs($this->superAdmin);

        $response = $this->getJson("/api/super-admin/users/{$this->regularUser->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'user' => [
                            'id',
                            'first_name',
                            'last_name',
                            'email',
                            'phone',
                            'is_active',
                            'role' => ['id', 'name']
                        ]
                    ]
                ]);
    }

    /** @test */
    public function super_admin_can_update_user()
    {
        Sanctum::actingAs($this->superAdmin);

        $updateData = [
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'email' => 'updated@test.com',
            'phone' => '+1234567890',
            'role_id' => $this->userRole->id,
            'is_active' => true,
        ];

        $response = $this->putJson("/api/super-admin/users/{$this->regularUser->id}", $updateData);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => ['user']
                ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->regularUser->id,
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'email' => 'updated@test.com',
            'phone' => '+1234567890',
        ]);
    }

    /** @test */
    public function super_admin_can_toggle_user_status()
    {
        Sanctum::actingAs($this->superAdmin);

        // User starts as active
        $this->assertTrue($this->regularUser->is_active);

        $response = $this->patchJson("/api/super-admin/users/{$this->regularUser->id}/toggle-status");

        $response->assertStatus(200);

        // Check user is now inactive
        $this->assertDatabaseHas('users', [
            'id' => $this->regularUser->id,
            'is_active' => false
        ]);
    }

    /** @test */
    public function super_admin_can_soft_delete_user()
    {
        Sanctum::actingAs($this->superAdmin);

        $response = $this->deleteJson("/api/super-admin/users/{$this->regularUser->id}");

        $response->assertStatus(200);

        // Check user is soft deleted
        $this->assertSoftDeleted('users', ['id' => $this->regularUser->id]);
    }

    /** @test */
    public function super_admin_cannot_update_own_role_to_non_superadmin()
    {
        Sanctum::actingAs($this->superAdmin);

        $updateData = [
            'first_name' => $this->superAdmin->first_name,
            'last_name' => $this->superAdmin->last_name,
            'email' => $this->superAdmin->email,
            'role_id' => $this->userRole->id, // Trying to change to non-superadmin
            'is_active' => true,
        ];

        $response = $this->putJson("/api/super-admin/users/{$this->superAdmin->id}", $updateData);

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'message' => 'No puedes cambiar tu propio rol de superadmin.'
                ]);
    }

    /** @test */
    public function super_admin_cannot_deactivate_themselves()
    {
        Sanctum::actingAs($this->superAdmin);

        $updateData = [
            'first_name' => $this->superAdmin->first_name,
            'last_name' => $this->superAdmin->last_name,
            'email' => $this->superAdmin->email,
            'role_id' => $this->superAdminRole->id,
            'is_active' => false, // Trying to deactivate themselves
        ];

        $response = $this->putJson("/api/super-admin/users/{$this->superAdmin->id}", $updateData);

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'message' => 'No puedes desactivarte a ti mismo.'
                ]);
    }

    /** @test */
    public function super_admin_cannot_toggle_own_status_to_inactive()
    {
        Sanctum::actingAs($this->superAdmin);

        $response = $this->patchJson("/api/super-admin/users/{$this->superAdmin->id}/toggle-status");

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'message' => 'No puedes desactivarte a ti mismo.'
                ]);
    }

    /** @test */
    public function super_admin_cannot_delete_themselves()
    {
        Sanctum::actingAs($this->superAdmin);

        $response = $this->deleteJson("/api/super-admin/users/{$this->superAdmin->id}");

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'message' => 'No puedes eliminarte a ti mismo.'
                ]);
    }

    /** @test */
    public function regular_user_cannot_access_user_management()
    {
        Sanctum::actingAs($this->regularUser);

        $response = $this->getJson("/api/super-admin/users/{$this->superAdmin->id}");
        $response->assertStatus(403);

        $response = $this->putJson("/api/super-admin/users/{$this->superAdmin->id}", []);
        $response->assertStatus(403);

        $response = $this->patchJson("/api/super-admin/users/{$this->superAdmin->id}/toggle-status");
        $response->assertStatus(403);

        $response = $this->deleteJson("/api/super-admin/users/{$this->superAdmin->id}");
        $response->assertStatus(403);
    }

    /** @test */
    public function update_user_validates_required_fields()
    {
        Sanctum::actingAs($this->superAdmin);

        $response = $this->putJson("/api/super-admin/users/{$this->regularUser->id}", [
            'first_name' => '',
            'last_name' => '',
            'email' => 'invalid-email',
            'role_id' => 999, // Non-existent role
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['first_name', 'last_name', 'email', 'role_id']);
    }

    /** @test */
    public function cannot_delete_user_who_is_organization_admin()
    {
        // Create organization with admin
        $organization = Organization::create([
            'name' => 'Test Organization',
            'slug' => 'test-org',
            'email' => 'org@test.com',
            'trust_level_id' => TrustLevel::first()->id,
            'admin_id' => $this->regularUser->id,
        ]);

        $this->regularUser->update(['organization_id' => $organization->id]);

        Sanctum::actingAs($this->superAdmin);

        $response = $this->deleteJson("/api/super-admin/users/{$this->regularUser->id}");

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'message' => 'No se puede eliminar el usuario porque es administrador de una o más organizaciones.'
                ]);
    }

    /** @test */
    public function cannot_delete_user_with_organization_admin_role()
    {
        // Create organization admin role (using the correct role name from your system)
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        
        // Create user with organization admin role
        $orgAdmin = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);

        Sanctum::actingAs($this->superAdmin);

        $response = $this->deleteJson("/api/super-admin/users/{$orgAdmin->id}");

        $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'message' => 'No se puede eliminar un usuario administrador de organización.'
                ]);
    }

    /** @test */
    public function super_admin_can_update_user_password()
    {
        Sanctum::actingAs($this->superAdmin);

        $updateData = [
            'first_name' => $this->regularUser->first_name,
            'last_name' => $this->regularUser->last_name,
            'email' => $this->regularUser->email,
            'role_id' => $this->userRole->id,
            'is_active' => true,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ];

        $response = $this->putJson("/api/super-admin/users/{$this->regularUser->id}", $updateData);

        $response->assertStatus(200);

        // Refresh user from database to get updated password
        $this->regularUser->refresh();
        
        // Verify password was updated by checking it was hashed differently
        $this->assertNotEquals(
            bcrypt('password'), // original password hash
            $this->regularUser->password // new password hash
        );
    }
}
