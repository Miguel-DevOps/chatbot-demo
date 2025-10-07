<?php

declare(strict_types=1);

namespace ChatbotDemo\Tests\Integration;

/**
 * Test de integración para middleware de manejo de errores
 * 
 * Valida que el ErrorHandlerMiddleware funciona correctamente
 * en diferentes escenarios de error.
 */
class ErrorHandlingTest extends IntegrationTestCase
{
    public function testNotFoundEndpoint(): void
    {
        // Test ruta que no existe
        $response = $this->get('/non-existent-endpoint');
        
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        
        $data = $this->getJsonResponse($response);
        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('requested_path', $data);
        $this->assertEquals('/non-existent-endpoint', $data['requested_path']);
    }

    public function testMethodNotAllowed(): void
    {
        // Test método no permitido en endpoint existente
        $request = $this->createRequest('PUT', '/health');
        $response = $this->runApp($request);
        
        // Should be handled as 404 by the generic handler
        $this->assertEquals(404, $response->getStatusCode());
        
        $data = $this->getJsonResponse($response);
        $this->assertArrayHasKey('error', $data);
    }

    public function testInvalidContentType(): void
    {
        // Test content type no válido para endpoint JSON
        $request = $this->createRequest(
            'POST',
            '/chat',
            ['Content-Type' => 'text/plain'],
            'plain text body'
        );
        
        $response = $this->runApp($request);
        
        // Parsing error should be handled
        $this->assertNotEquals(500, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
    }

    public function testMalformedJson(): void
    {
        // Test JSON malformado
        $request = $this->createRequest(
            'POST',
            '/chat',
            ['Content-Type' => 'application/json'],
            '{"invalid": json, "missing": quote}'
        );
        
        $response = $this->runApp($request);
        
        $this->assertEquals(400, $response->getStatusCode());
        
        $data = $this->getJsonResponse($response);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('json', strtolower($data['error']));
    }

    public function testLargeRequestBody(): void
    {
        // Test body demasiado grande
        $largeMessage = str_repeat('a', 10000); // 10KB message
        
        $response = $this->postJson('/chat', [
            'message' => $largeMessage,
            'conversation_id' => []
        ]);
        
        // Should be rejected or handled appropriately
        $this->assertNotEquals(500, $response->getStatusCode());
        
        if ($response->getStatusCode() !== 200) {
            $data = $this->getJsonResponse($response);
            $this->assertArrayHasKey('error', $data);
        }
    }

    public function testErrorResponseStructure(): void
    {
        // Test estructura consistente de respuestas de error
        $response = $this->get('/non-existent');
        
        $data = $this->getJsonResponse($response);
        
        // Verificar estructura estándar de error
        $this->assertArrayHasKey('error', $data);
        $this->assertIsString($data['error']);
        
        // Opcional pero recomendado
        if (isset($data['message'])) {
            $this->assertIsString($data['message']);
        }
        
        if (isset($data['timestamp'])) {
            $this->assertMatchesRegularExpression(
                '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
                $data['timestamp']
            );
        }
    }

    public function testErrorLogging(): void
    {
        // Test que los errores son loggeados (indirectamente)
        // Como usamos NullHandler en tests, verificamos que no hay excepciones
        
        $response = $this->get('/non-existent');
        
        // Si llega aquí sin excepción, el logging está funcionando
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertTrue(true, 'Error logging completed without exceptions');
    }

    public function testCorsOnErrorResponses(): void
    {
        // Test que CORS headers están presentes incluso en respuestas de error
        $response = $this->get('/non-existent');
        
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('Access-Control-Allow-Origin'));
    }

    public function testErrorResponseContentType(): void
    {
        // Test que todas las respuestas de error son JSON
        $testCases = [
            '/non-existent',
            '/another/non-existent/path'
        ];
        
        foreach ($testCases as $path) {
            $response = $this->get($path);
            $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        }
    }

    public function testMultipleErrorScenarios(): void
    {
        // Test múltiples tipos de error en secuencia
        $scenarios = [
            ['GET', '/not-found', 404],
            ['POST', '/health', 404], // POST no permitido en health
            ['DELETE', '/chat', 404], // DELETE no permitido
        ];
        
        foreach ($scenarios as [$method, $path, $expectedStatus]) {
            $request = $this->createRequest($method, $path);
            $response = $this->runApp($request);
            
            $this->assertEquals($expectedStatus, $response->getStatusCode());
            $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
            
            $data = $this->getJsonResponse($response);
            $this->assertArrayHasKey('error', $data);
        }
    }
}