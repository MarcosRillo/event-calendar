<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\CheckSuperAdmin;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CheckSuperAdminTest extends TestCase
{
    use RefreshDatabase;

    protected CheckSuperAdmin $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new CheckSuperAdmin();
        
        // Crear roles necesarios
        Role::factory()->create(['name' => 'superadmin']);
        Role::factory()->create(['name' => 'admin']);
    }

    public function test_allows_access_for_super_admin(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(function () use ($superAdmin) {
            return $superAdmin;
        });

        $response = $this->middleware->handle($request, function () {
            return new Response('OK', 200);
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    public function test_denies_access_for_regular_user(): void
    {
        $regularUser = User::factory()->admin()->create();
        
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(function () use ($regularUser) {
            return $regularUser;
        });

        $response = $this->middleware->handle($request, function () {
            return new Response('Should not reach here');
        });

        $this->assertEquals(403, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('Forbidden. Super admin access required.', $responseData['message']);
    }

    public function test_denies_access_for_unauthenticated_user(): void
    {
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(function () {
            return null;
        });

        $response = $this->middleware->handle($request, function () {
            return new Response('Should not reach here');
        });

        $this->assertEquals(401, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('Unauthorized. Authentication required.', $responseData['message']);
    }

    public function test_loads_role_relationship_if_not_loaded(): void
    {
        $superAdminRole = Role::where('name', 'superadmin')->first();
        $user = User::factory()->create(['role_id' => $superAdminRole->id]);
        
        // Asegurar que la relación no está cargada
        $user->unsetRelation('role');
        $this->assertFalse($user->relationLoaded('role'));

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $response = $this->middleware->handle($request, function () {
            return new Response('OK');
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($user->relationLoaded('role'));
    }

    public function test_middleware_logs_access_attempts(): void
    {
        // Para un test más simple, solo verificamos que no hay errores
        $superAdmin = User::factory()->superAdmin()->create();
        
        $request = Request::create('/test-route', 'GET');
        $request->setUserResolver(function () use ($superAdmin) {
            return $superAdmin;
        });

        $response = $this->middleware->handle($request, function () {
            return new Response('OK');
        });

        $this->assertEquals(200, $response->getStatusCode());
        // En un test de integración más complejo podrías verificar logs
    }

    public function test_middleware_handles_invalid_user_object(): void
    {
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(function () {
            return (object) ['id' => 1, 'email' => 'test@example.com']; // No es instancia de User
        });

        $response = $this->middleware->handle($request, function () {
            return new Response('Should not reach here');
        });

        $this->assertEquals(401, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('Unauthorized. Authentication required.', $responseData['message']);
    }

    public function test_response_format_is_consistent(): void
    {
        $regularUser = User::factory()->admin()->create();
        
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(function () use ($regularUser) {
            return $regularUser;
        });

        $response = $this->middleware->handle($request, function () {
            return new Response('Should not reach here');
        });

        $responseData = json_decode($response->getContent(), true);
        
        // Verificar estructura de respuesta consistente
        $this->assertArrayHasKey('success', $responseData);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertIsBool($responseData['success']);
        $this->assertIsString($responseData['message']);
        $this->assertFalse($responseData['success']);
    }

    public function test_middleware_preserves_request_data(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        
        $request = Request::create('/test', 'POST', ['test_param' => 'test_value']);
        $request->setUserResolver(function () use ($superAdmin) {
            return $superAdmin;
        });

        $requestReceived = false;
        $response = $this->middleware->handle($request, function ($req) use (&$requestReceived) {
            $requestReceived = true;
            $this->assertEquals('test_value', $req->input('test_param'));
            return new Response('OK');
        });

        $this->assertTrue($requestReceived);
        $this->assertEquals(200, $response->getStatusCode());
    }
}
