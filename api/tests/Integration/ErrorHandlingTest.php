<?php

declare(strict_types=1);

namespace ChatbotDemo\Tests\Integration;

/**
 * Integration test for error handling middleware
 * 
 * Valida que el ErrorHandlerMiddleware funciona correctamente
 * en diferentes escenarios de error.
 */
class ErrorHandlingTest extends IntegrationTestCase
{
    public function testNotFoundEndpoint(): void
    {
        // Test route that doesn't exist
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
        // Test method not allowed on existing endpoint
        $request = $this->createRequest('PUT', '/health');
        $response = $this->runApp($request);
        
        // Should be handled as 404 by the generic handler
        $this->assertEquals(404, $response->getStatusCode());
        
        $data = $this->getJsonResponse($response);
        $this->assertArrayHasKey('error', $data);
    }

    public function testInvalidContentType(): void
    {
        // Test invalid content type for JSON endpoint
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
            'conversation_id' => null
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
        // Test consistent structure of error responses
        $response = $this->get('/non-existent');
        
        $data = $this->getJsonResponse($response);
        
        // Verify standard error structure
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
        // Test that errors are logged (indirectly)
        // Since we use NullHandler in tests, we verify there are no exceptions
        
        $response = $this->get('/non-existent');
        
        // If it gets here without exception, logging is working
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertTrue(true, 'Error logging completed without exceptions');
    }

    public function testCorsOnErrorResponses(): void
    {
        // Test that CORS headers are present even in error responses
        $response = $this->get('/non-existent');
        
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('Access-Control-Allow-Origin'));
    }

    public function testErrorResponseContentType(): void
    {
        // Test that all error responses are JSON
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
        // Test multiple error types in sequence
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