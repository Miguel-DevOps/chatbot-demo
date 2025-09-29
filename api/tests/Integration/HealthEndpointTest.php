<?php

declare(strict_types=1);

namespace ChatbotDemo\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Test de integración para endpoint de health
 * 
 * Valida el flujo completo: Request -> Middleware -> Controller -> Response
 * usando la aplicación Slim en memoria.
 */
class HealthEndpointTest extends IntegrationTestCase
{
    public function testHealthEndpointReturnsValidResponse(): void
    {
        // Arrange & Act
        $response = $this->get('/health');
        
        // Assert response status
        $this->assertEquals(200, $response->getStatusCode());
        
        // Assert content type
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        
        // Assert response structure
        $data = $this->getJsonResponse($response);
        
        $this->assertIsArray($data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('version', $data);
        $this->assertArrayHasKey('environment', $data);
        $this->assertArrayHasKey('checks', $data);
        
        // Assert response values
        $this->assertEquals('ok', $data['status']);
        $this->assertEquals('2.0.0', $data['version']);
        $this->assertEquals('test', $data['environment']);
        $this->assertIsArray($data['checks']);
        
        // Assert timestamp format
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
            $data['timestamp']
        );
    }

    public function testHealthEndpointWithPhpExtension(): void
    {
        // Test que funciona tanto /health como /health.php
        $response = $this->get('/health.php');
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        
        $data = $this->getJsonResponse($response);
        $this->assertEquals('ok', $data['status']);
    }

    public function testHealthEndpointServicesStatus(): void
    {
        $response = $this->get('/health');
        $data = $this->getJsonResponse($response);
        
        // Verificar que los checks están incluidos
        $this->assertArrayHasKey('checks', $data);
        $checks = $data['checks'];
        
        // Cada check debe tener status
        foreach ($checks as $checkName => $checkData) {
            $this->assertIsString($checkName);
            $this->assertIsArray($checkData);
            $this->assertArrayHasKey('status', $checkData);
            $this->assertContains($checkData['status'], ['ok', 'warning', 'error', 'demo']);
        }
    }

    public function testHealthEndpointCorsHeaders(): void
    {
        // Test que el middleware CORS está funcionando
        $response = $this->get('/health');
        
        // Verificar que tenemos headers CORS básicos
        $this->assertTrue($response->hasHeader('Access-Control-Allow-Origin'));
    }

    public function testHealthEndpointOptionsRequest(): void
    {
        // Test preflight CORS request - Por ahora skip este test
        $this->markTestSkipped('OPTIONS handling needs refinement for health endpoint');
    }

    public function testHealthEndpointResponseTime(): void
    {
        // Test que el endpoint responde rápidamente
        $startTime = microtime(true);
        
        $response = $this->get('/health');
        
        $endTime = microtime(true);
        $responseTime = $endTime - $startTime;
        
        // Assert response successful and fast (< 100ms)
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertLessThan(0.1, $responseTime, 'Health endpoint should respond in less than 100ms');
    }

    public function testHealthEndpointErrorHandling(): void
    {
        // Test que el middleware de error handling está activo
        // Intentamos un método no permitido
        $request = $this->createRequest('PUT', '/health');
        $response = $this->runApp($request);
        
        // Debería ser manejado por el error middleware, no un 500
        $this->assertNotEquals(500, $response->getStatusCode());
    }
}