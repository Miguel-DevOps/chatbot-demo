<?php

declare(strict_types=1);

namespace ChatbotDemo\Tests\Integration;

use ChatbotDemo\Middleware\ValidationMiddleware;
use ChatbotDemo\Middleware\RateLimitMiddleware;
use ChatbotDemo\Controllers\ChatController;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ResponseFactory;

/**
 * Integration tests for middleware stack and controller interactions
 */
class MiddlewareIntegrationTest extends IntegrationTestCase
{
    public function testValidationMiddlewareIntegration(): void
    {
        // Test validation middleware with various message types
        
        $validMessages = [
            'Hola, ¿cómo estás?',
            'I need help with my project.',
            'Información sobre servicios.',
            '¿Puedes ayudarme?',
            'Hello world! 😊'
        ];
        
        foreach ($validMessages as $message) {
            $response = $this->postJson('/chat', [
                'message' => $message,
                'conversation_id' => 'test-' . uniqid()
            ]);
            
            $this->assertEquals(200, $response->getStatusCode(), 
                "Valid message should pass validation: $message");
        }
    }

    public function testValidationMiddlewareSecurityBlocking(): void
    {
        // Test that security validation blocks malicious content
        
        $maliciousMessages = [
            '<script>alert("xss")</script>',
            'DROP TABLE users;',
            'SELECT * FROM passwords',
            '<?php system("rm -rf /"); ?>',
            'javascript:void(0)',
            'data:text/html,<script>alert(1)</script>'
        ];
        
        foreach ($maliciousMessages as $message) {
            $response = $this->postJson('/chat', [
                'message' => $message,
                'conversation_id' => 'test-' . uniqid()
            ]);
            
            $this->assertEquals(400, $response->getStatusCode(), 
                "Malicious message should be blocked: $message");
            
            $data = $this->getJsonResponse($response);
            $this->assertArrayHasKey('error', $data);
            $this->assertStringContainsString('contenido no permitido', $data['error']);
        }
    }

    public function testRateLimitingControllerIntegration(): void
    {
        // Test rate limiting in controller with actual requests
        
        // Override configuration for this test with in-memory storage
        $this->setTestConfigOverrides([
            'rate_limit' => [
                'max_requests' => 3,
                'time_window' => 60
            ],
            'use_memory_rate_limit' => true
        ]);
        
        // Reconfigure app with new settings
        $this->setupApplication();
        
        $conversationId = 'rate-limit-test-' . time();
        
        // Send exactly the rate limit number of requests
        for ($i = 1; $i <= 3; $i++) {
            $response = $this->postJson('/chat', [
                'message' => "Test message $i",
                'conversation_id' => $conversationId
            ]);
            
            $this->assertEquals(200, $response->getStatusCode(), 
                "Request $i should be allowed");
        }
        
        // The next request should be rate limited
        $response = $this->postJson('/chat', [
            'message' => 'This should be rate limited',
            'conversation_id' => $conversationId
        ]);
        
        $this->assertEquals(429, $response->getStatusCode(), 
            "Request exceeding limit should be rate limited");
        
        $this->assertTrue($response->hasHeader('Retry-After'));
        
        $data = $this->getJsonResponse($response);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('límite', strtolower($data['error']));
    }

    public function testMiddlewareStackOrder(): void
    {
        // Test that middleware executes in correct order
        // 1. Validation should run first
        // 2. Rate limiting should run second
        // 3. Controller should run last
        
        // Send invalid message - should be caught by validation before rate limiting
        $response = $this->postJson('/chat', [
            'message' => '<script>alert("test")</script>',
            'conversation_id' => 'order-test'
        ]);
        
        $this->assertEquals(400, $response->getStatusCode());
        
        // Rate limiting should not be checked if validation fails
        // (validation happens first in middleware stack)
    }

    public function testErrorHandlingIntegration(): void
    {
        // Test error handling across the middleware stack
        
        $testCases = [
            // Missing message
            [
                'payload' => ['conversation_id' => 'test'],
                'expectedStatus' => 400,
                'expectedError' => 'requerido'
            ],
            // Empty message
            [
                'payload' => ['message' => '', 'conversation_id' => 'test'],
                'expectedStatus' => 400,
                'expectedError' => 'vacío'
            ],
            // Missing conversation_id - this should still work as it's optional
            [
                'payload' => ['message' => 'Hello'],
                'expectedStatus' => 200, // Changed: conversation_id is optional
                'expectedError' => null
            ],
            // Invalid JSON structure
            [
                'payload' => 'invalid-json',
                'expectedStatus' => 400,
                'expectedError' => 'Formato JSON'
            ]
        ];
        
        foreach ($testCases as $i => $testCase) {
            if (is_string($testCase['payload'])) {
                // Send raw string instead of JSON
                $request = $this->createRequest('POST', '/chat', [
                    'Content-Type' => 'application/json'
                ], $testCase['payload']);
                $response = $this->runApp($request);
            } else {
                $response = $this->postJson('/chat', $testCase['payload']);
            }
            
            $this->assertEquals($testCase['expectedStatus'], $response->getStatusCode(), 
                "Test case $i should return expected status");
            
            if ($testCase['expectedStatus'] !== 200) {
                $data = $this->getJsonResponse($response);
                $this->assertArrayHasKey('error', $data, 
                    "Test case $i should return error");
                    
                if ($testCase['expectedError'] !== null) {
                    $this->assertStringContainsString(
                        $testCase['expectedError'], 
                        $data['error'], 
                        "Test case $i should contain expected error message"
                    );
                }
            }
        }
    }

    public function testCorsMiddlewareIntegration(): void
    {
        // Test CORS handling in the middleware stack
        
        // OPTIONS request should be handled by CORS middleware
        $request = $this->createRequest('OPTIONS', '/chat', [
            'Origin' => 'http://localhost:3000',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'Content-Type'
        ]);
        
        $response = $this->runApp($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('Access-Control-Allow-Origin'));
        $this->assertTrue($response->hasHeader('Access-Control-Allow-Methods'));
        $this->assertTrue($response->hasHeader('Access-Control-Allow-Headers'));
    }

    public function testHealthCheckBypassesMiddleware(): void
    {
        // Test that health check endpoint bypasses rate limiting and validation
        
        // First, exhaust rate limit on chat endpoint
        $this->reconfigureApp([
            'rate_limit' => [
                'max_requests' => 1,
                'time_window' => 60
            ]
        ]);
        
        // Use up the rate limit
        $this->postJson('/chat', [
            'message' => 'Test message',
            'conversation_id' => 'exhaust-test'
        ]);
        
        // Health check should still work
        $response = $this->get('/health');
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = $this->getJsonResponse($response);
        $this->assertEquals('ok', $data['status']);
    }

    public function testContentNegotiationIntegration(): void
    {
        // Test that the API handles different content types correctly
        
        // Valid JSON request
        $response = $this->postJson('/chat', [
            'message' => 'Hello',
            'conversation_id' => 'content-test'
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', 
            $response->getHeaderLine('Content-Type'));
        
        // Request with wrong content type should be rejected
        $request = $this->createRequest('POST', '/chat', [
            'Content-Type' => 'application/x-www-form-urlencoded'
        ], 'message=hello');
        $response = $this->runApp($request);
        
        $this->assertEquals(400, $response->getStatusCode());
    }
}