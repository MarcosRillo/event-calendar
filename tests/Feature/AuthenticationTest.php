<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Crear roles básicos para los tests
        Role::factory()->create(['name' => 'superadmin']);
        Role::factory()->create(['name' => 'admin']);
        Role::factory()->create(['name' => 'organization_admin']);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123')
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'token',
                    'user' => [
                        'id', 
                        'name', 
                        'email', 
                        'role',
                        'is_super_admin',
                        'is_organization_admin'
                    ],
                    'message'
                ])
                ->assertJson([
                    'success' => true,
                    'user' => [
                        'email' => 'test@example.com'
                    ]
                ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class
        ]);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123')
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword'
        ]);

        $response->assertStatus(401)
                ->assertJson([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ]);
    }

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/login', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_requires_valid_email_format(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'invalid-email',
            'password' => 'password123'
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/logout');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Logout successful'
                ]);

        // Verificar que el token fue eliminado
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class
        ]);
    }

    public function test_authenticated_user_can_get_profile(): void
    {
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user, 'sanctum')
                        ->getJson('/api/user');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'user' => [
                        'id',
                        'name', 
                        'email',
                        'role',
                        'is_super_admin',
                        'is_organization_admin'
                    ]
                ])
                ->assertJson([
                    'success' => true,
                    'user' => [
                        'id' => $user->id,
                        'email' => $user->email,
                        'is_super_admin' => true
                    ]
                ]);
    }

    public function test_unauthenticated_user_cannot_access_protected_routes(): void
    {
        $response = $this->getJson('/api/user');

        $response->assertStatus(401);
    }

    public function test_super_admin_user_has_correct_permissions(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($superAdmin, 'sanctum')
                        ->getJson('/api/user');

        $response->assertStatus(200)
                ->assertJson([
                    'user' => [
                        'is_super_admin' => true,
                        'role' => 'superadmin'
                    ]
                ]);
    }

    public function test_regular_user_does_not_have_super_admin_permissions(): void
    {
        $regularUser = User::factory()->admin()->create();

        $response = $this->actingAs($regularUser, 'sanctum')
                        ->getJson('/api/user');

        $response->assertStatus(200)
                ->assertJson([
                    'user' => [
                        'is_super_admin' => false,
                        'role' => 'admin'
                    ]
                ]);
    }

    public function test_login_rate_limiting(): void
    {
        $this->markTestSkipped('Rate limiting requires cache configuration - will be implemented in production setup');
        
        // Hacer exactamente 5 intentos de login fallidos (el límite)
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/login', [
                'email' => 'test@example.com',
                'password' => 'wrongpassword'
            ]);
            $this->assertEquals(401, $response->getStatusCode());
        }

        // El siguiente intento debería ser rechazado por rate limiting
        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword'
        ]);

        $response->assertStatus(429); // Too Many Requests
    }
}
