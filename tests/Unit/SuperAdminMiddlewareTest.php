<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use App\Http\Middleware\SuperAdminMiddleware;
use App\Models\User;
use App\Models\Role;

class SuperAdminMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new SuperAdminMiddleware();
    }

    /** @test */
    public function it_returns_401_when_user_is_not_authenticated()
    {
        Auth::shouldReceive('check')->once()->andReturn(false);

        $request = Request::create('/test', 'GET');
        $response = $this->middleware->handle($request, function () {
            return new Response('OK');
        });

        $this->assertEquals(401, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('UNAUTHENTICATED', $responseData['error']);
    }

    /** @test */
    public function it_returns_401_when_user_is_not_valid_user_instance()
    {
        Auth::shouldReceive('check')->once()->andReturn(true);
        Auth::shouldReceive('user')->once()->andReturn((object) ['id' => 1]);

        $request = Request::create('/test', 'GET');
        $response = $this->middleware->handle($request, function () {
            return new Response('OK');
        });

        $this->assertEquals(401, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('INVALID_USER', $responseData['error']);
    }

        /** @test */
    public function it_returns_403_when_user_is_not_super_admin()
    {
        // Arrange
        $regularRole = Role::factory()->create(['name' => 'admin']);
        $user = User::factory()->create(['role_id' => $regularRole->id]);
        $user->load('role');

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $request = new Request();
        $middleware = new SuperAdminMiddleware();

        // Act
        $response = $middleware->handle($request, function () {
            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertEquals(403, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('INSUFFICIENT_PRIVILEGES', $responseData['error']);
    }

    /** @test */
    public function it_allows_access_when_user_is_super_admin()
    {
        $role = Role::factory()->create(['name' => 'superadmin']);
        $user = User::factory()->create(['role_id' => $role->id]);
        $user->load('role');

        Auth::shouldReceive('check')->once()->andReturn(true);
        Auth::shouldReceive('user')->once()->andReturn($user);

        $request = Request::create('/test', 'GET');
        $response = $this->middleware->handle($request, function () {
            return new Response('OK');
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    /** @test */
    public function it_loads_role_relationship_if_not_loaded()
    {
        $role = Role::factory()->create(['name' => 'superadmin']);
        $user = User::factory()->create(['role_id' => $role->id]);
        // No cargar la relación role intencionalmente

        // Simular que la relación no está cargada
        $user->unsetRelation('role');

        Auth::shouldReceive('check')->once()->andReturn(true);
        Auth::shouldReceive('user')->once()->andReturn($user);

        $request = Request::create('/test', 'GET');
        $response = $this->middleware->handle($request, function () {
            return new Response('OK');
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($user->relationLoaded('role'));
    }
}
