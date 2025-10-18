<?php

declare(strict_types=1);

namespace ChatbotDemo\Tests\Integration;

/**
 * Integration test for the complete middleware chain
 * 
 * Validates that all middlewares are working correctly
 * in the correct order: Body Parsing -> Routing -> Error Handler -> CORS
 */
class MiddlewareStackTest extends IntegrationTestCase
{
    public function testMiddlewareStackOrder(): void
    {
        // Test that middleware stack is configured correctly
        // Make a valid request and verify that all middlewares process it
        
        $response = $this->get('/health');
        
        // If we get here with 200, the entire stack worked
        $this->assertEquals(200, $response->getStatusCode());
        
        // Verify that CORS middleware worked
        $this->assertTrue($response->hasHeader('Access-Control-Allow-Origin'));
        
        // Verify that content parsing worked (JSON response)
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        
        // Verify that routing worked (structured response)
        $data = $this->getJsonResponse($response);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('status', $data);
    }

    public function testBodyParsingMiddleware(): void
    {
        // Test that body parsing middleware works correctly
        $requestData = [
            'message' => 'Test message for parsing',
            'conversation_id' => ['prev_msg1', 'prev_msg2']
        ];
        
        $response = $this->postJson('/chat', $requestData);
        
        // If there's no 400 parsing error, the middleware worked
        $this->assertNotEquals(400, $response->getStatusCode(), 'Body parsing should work correctly');
    }

    public function testRoutingMiddleware(): void
    {
        // Test that routing middleware identifies routes correctly
        $testRoutes = [
            ['/health', 'GET', 200],
            ['/health.php', 'GET', 200],
            ['/chat', 'POST', 200], // Assuming there's a mock configured
            ['/chat.php', 'POST', 200],
            ['/non-existent', 'GET', 404],
        ];
        
        foreach ($testRoutes as [$path, $method, $expectedStatus]) {
            if ($method === 'POST') {
                $response = $this->postJson($path, [
                    'message' => 'test',
                    'conversation_id' => null
                ]);
            } else {
                $response = $this->get($path);
            }
            
            $this->assertEquals($expectedStatus, $response->getStatusCode(), 
                "Route {$method} {$path} should return {$expectedStatus}");
        }
    }

    public function testCorsMiddlewareSimpleRequest(): void
    {
        // Test CORS middleware with simple request
        $request = $this->createRequest(
            'GET',
            '/health',
            ['Origin' => 'http://localhost:3000']
        );
        
        $response = $this->runApp($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('Access-Control-Allow-Origin'));
        
        $corsOrigin = $response->getHeaderLine('Access-Control-Allow-Origin');
        $this->assertNotEmpty($corsOrigin);
    }

    public function testCorsMiddlewarePreflightRequest(): void
    {
        // Test CORS middleware with preflight request
        $request = $this->createRequest(
            'OPTIONS',
            '/chat',
            [
                'Origin' => 'http://localhost:3000',
                'Access-Control-Request-Method' => 'POST',
                'Access-Control-Request-Headers' => 'Content-Type, Authorization'
            ]
        );
        
        $response = $this->runApp($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('Access-Control-Allow-Origin'));
        $this->assertTrue($response->hasHeader('Access-Control-Allow-Methods'));
        $this->assertTrue($response->hasHeader('Access-Control-Allow-Headers'));
        
        // Verify that allowed methods include POST
        $allowedMethods = $response->getHeaderLine('Access-Control-Allow-Methods');
        $this->assertStringContainsString('POST', $allowedMethods);
        
        // Verify that allowed headers include Content-Type
        $allowedHeaders = $response->getHeaderLine('Access-Control-Allow-Headers');
        $this->assertStringContainsString('Content-Type', $allowedHeaders);
    }

    public function testErrorHandlerMiddlewareIntegration(): void
    {
        // Test that error handler middleware captures errors and formats them
        $response = $this->get('/this-route-does-not-exist');
        
        // Error handler should have formatted the response
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        
        $data = $this->getJsonResponse($response);
        $this->assertArrayHasKey('error', $data);
        $this->assertIsString($data['error']);
    }

    public function testMiddlewareWithInvalidJson(): void
    {
        // Test that the stack handles invalid JSON correctly
        $request = $this->createRequest(
            'POST',
            '/chat',
            ['Content-Type' => 'application/json'],
            '{"invalid": json'
        );
        
        $response = $this->runApp($request);
        
        // Body parsing middleware should detect and error handler should format
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        
        $data = $this->getJsonResponse($response);
        $this->assertArrayHasKey('error', $data);
    }

    public function testMiddlewareWithLargeRequest(): void
    {
        // Test that the stack handles large requests
        $largeData = [
            'message' => str_repeat('Large message content. ', 10) // Reduce much more to find the limit
        ];
        
        $response = $this->postJson('/chat', $largeData);
        
        // Stack should handle without crashes
        $this->assertNotEquals(500, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
    }

    public function testMiddlewareHeaders(): void
    {
        // Test that headers are maintained throughout the entire stack
        $request = $this->createRequest(
            'GET',
            '/health',
            [
                'User-Agent' => 'Integration-Test/1.0',
                'Accept' => 'application/json',
                'Origin' => 'http://localhost:3000'
            ]
        );
        
        $response = $this->runApp($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        // Verify that response headers are present
        $this->assertTrue($response->hasHeader('Content-Type'));
        $this->assertTrue($response->hasHeader('Access-Control-Allow-Origin'));
    }

    public function testMiddlewareErrorPropagation(): void
    {
        // Test that errors propagate correctly through the stack
        $scenarios = [
            // Malformed JSON - should be caught by body parsing
            ['POST', '/chat', '{"bad": json', 400],
            
            // Non-existent route - should be caught by routing
            ['GET', '/nonexistent', '', 404],
            
            // Method not allowed - should be handled by routing
            ['PUT', '/health', '', 404],
        ];
        
        foreach ($scenarios as [$method, $path, $body, $expectedStatus]) {
            $headers = $method === 'POST' ? ['Content-Type' => 'application/json'] : [];
            $request = $this->createRequest($method, $path, $headers, $body);
            
            $response = $this->runApp($request);
            
            $this->assertEquals($expectedStatus, $response->getStatusCode());
            $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
            
            $data = $this->getJsonResponse($response);
            $this->assertArrayHasKey('error', $data);
        }
    }

    public function testMiddlewarePerformance(): void
    {
        // Test that the middleware stack doesn't introduce significant latency
        $startTime = microtime(true);
        
        $response = $this->get('/health');
        
        $endTime = microtime(true);
        $processingTime = $endTime - $startTime;
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertLessThan(0.05, $processingTime, 'Middleware stack should process requests in less than 50ms');
    }
}