<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Http\Middleware\SuperAdminMiddleware;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SuperAdminMiddlewareBasicTest extends TestCase
{
    use RefreshDatabase;

    public function test_middleware_returns_401_when_not_authenticated()
    {
        // Arrange
        Auth::shouldReceive('check')->andReturn(false);
        
        $request = new Request();
        $middleware = new SuperAdminMiddleware();

        // Act
        $response = $middleware->handle($request, function () {
            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertEquals(401, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('UNAUTHENTICATED', $responseData['error']);
    }

    public function test_middleware_returns_401_when_user_is_invalid()
    {
        // Arrange
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn('invalid_user');
        
        $request = new Request();
        $middleware = new SuperAdminMiddleware();

        // Act
        $response = $middleware->handle($request, function () {
            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertEquals(401, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('INVALID_USER', $responseData['error']);
    }

    public function test_middleware_allows_access_for_super_admin()
    {
        // Arrange
        $superAdminRole = Role::factory()->create(['name' => 'superadmin']);
        $user = User::factory()->create(['role_id' => $superAdminRole->id]);
        
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);
        
        $request = new Request();
        $middleware = new SuperAdminMiddleware();

        // Act
        $response = $middleware->handle($request, function () {
            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
    }

    public function test_middleware_returns_403_for_regular_user()
    {
        // Arrange
        $regularRole = Role::factory()->create(['name' => 'admin']);
        $user = User::factory()->create(['role_id' => $regularRole->id]);
        
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
}
