<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;

class DebugTest extends TestCase
{
    public function test_validation_debug(): void
    {
        try {
            $controller = new AuthController();
            $request = Request::create('/api/login', 'POST', []);
            
            $response = $controller->login($request);
            
            echo "\n=== Debug Info ===\n";
            echo "Direct Controller Call:\n";
            echo "Status: " . $response->getStatusCode() . "\n";
            echo "Content: " . $response->getContent() . "\n";
            echo "=================\n";
        } catch (\Exception $e) {
            echo "\n=== Exception Debug ===\n";
            echo "Exception: " . $e->getMessage() . "\n";
            echo "File: " . $e->getFile() . "\n";
            echo "Line: " . $e->getLine() . "\n";
            echo "Trace: " . $e->getTraceAsString() . "\n";
            echo "==================\n";
        }
        
        $response = $this->postJson('/api/login', []);
        
        echo "\n=== Via HTTP ===\n";
        echo "App Environment: " . app()->environment() . "\n";
        echo "Status: " . $response->getStatusCode() . "\n";
        echo "Content: " . $response->getContent() . "\n";
        echo "==============\n";
        
        // Solo verificar que hemos llegado aquí
        $this->assertTrue(true);
    }
}
