<?php

declare(strict_types=1);

namespace ChatbotDemo\Tests\Integration;

use ChatbotDemo\Config\AppConfig;
use ChatbotDemo\Services\ChatService;
use ChatbotDemo\Services\GenerativeAiClientInterface;
use ChatbotDemo\Services\KnowledgeBaseService;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use OpenTelemetry\API\Trace\TracerInterface;

/**
 * Integration test for chat endpoint
 * 
 * Valida el flujo completo mientras mockea selectivamente dependencias externas.
 * Testing completo: Request -> Middleware -> Controller -> Service -> Response
 */
class ChatEndpointTest extends IntegrationTestCase
{
    private MockObject $mockChatService;
    private bool $useRealChatService = false;
    private ?array $testRateLimitConfig = null;
    private bool $useInMemoryRateLimitStorage = false;

    protected function setUp(): void
    {
        // Create ChatService mock before setup
        $this->mockChatService = $this->createMock(ChatService::class);
        
        parent::setUp();
    }

    /**
     * Override del factory method para inyectar el mock o servicio real
     */
    protected function createChatService(GenerativeAiClientInterface $aiClient, KnowledgeBaseService $knowledgeService, LoggerInterface $logger, TracerInterface $tracer): ChatService
    {
        if ($this->useRealChatService) {
            return new ChatService($aiClient, $knowledgeService, $logger, $tracer);
        }
        return $this->mockChatService;
    }

    public function testChatEndpointValidRequest(): void
    {
        // Arrange
        $expectedResponse = [
            'response' => 'Esta es una respuesta de prueba del chatbot.',
            'timestamp' => date('c'),
            'model' => 'gemini-pro'
        ];

        $this->mockChatService
            ->expects($this->once())
            ->method('processMessage')
            ->with(
                $this->equalTo('Hola, ¿cómo estás?'),
                $this->equalTo(null),
                $this->anything()
            )
            ->willReturn($expectedResponse);

        // Act
        $response = $this->postJson('/chat', [
            'message' => 'Hola, ¿cómo estás?',
            'conversation_id' => null
        ]);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));

        $data = $this->getJsonResponse($response);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('response', $data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertEquals($expectedResponse['response'], $data['response']);
    }

    public function testChatEndpointWithPhpExtension(): void
    {
        // Test that both /chat and /chat.php work
        $this->mockChatService
            ->expects($this->once())
            ->method('processMessage')
            ->with(
                $this->equalTo('Test message'),
                $this->equalTo(null),
                $this->anything()
            )
            ->willReturn([
                'response' => 'Test response',
                'timestamp' => date('c'),
                'model' => 'gemini-pro'
            ]);

        $response = $this->postJson('/chat.php', [
            'message' => 'Test message',
            'conversation_id' => null
        ]);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testChatEndpointInvalidJsonRequest(): void
    {
        // Test invalid JSON handling by middleware
        $request = $this->createRequest(
            'POST',
            '/chat',
            ['Content-Type' => 'application/json'],
            '{"invalid": json}' // JSON malformado
        );

        $response = $this->runApp($request);

        // Error handling middleware should handle this
        $this->assertEquals(400, $response->getStatusCode());
        
        $data = $this->getJsonResponse($response);
        $this->assertArrayHasKey('error', $data);
    }

    public function testChatEndpointMissingMessage(): void
    {
        // Test required parameters validation
        $response = $this->postJson('/chat', [
            'conversation_id' => null
            // Falta 'message'
        ]);

        $this->assertEquals(400, $response->getStatusCode());
        
        $data = $this->getJsonResponse($response);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('message', strtolower($data['error']));
    }

    public function testChatEndpointEmptyMessage(): void
    {
        // Test empty message validation
        $response = $this->postJson('/chat', [
            'message' => '',
            'conversation_id' => null
        ]);

        $this->assertEquals(400, $response->getStatusCode());
        
        $data = $this->getJsonResponse($response);
        $this->assertArrayHasKey('error', $data);
    }

    public function testChatEndpointInvalidMessageType(): void
    {
        // Test message type validation
        $response = $this->postJson('/chat', [
            'message' => 123, // No es string
            'conversation_id' => null
        ]);

        $this->assertEquals(400, $response->getStatusCode());
        
        $data = $this->getJsonResponse($response);
        $this->assertArrayHasKey('error', $data);
    }

    public function testChatEndpointServiceException(): void
    {
        // Test service exception handling
        $this->mockChatService
            ->expects($this->once())
            ->method('processMessage')
            ->with(
                $this->equalTo('Test message'),
                $this->equalTo(null),
                $this->anything()
            )
            ->willThrowException(new \Exception('Simulated service error'));

        $response = $this->postJson('/chat', [
            'message' => 'Test message',
            'conversation_id' => null
        ]);

        // Error handling middleware should capture and format
        $this->assertEquals(500, $response->getStatusCode());
        
        $data = $this->getJsonResponse($response);
        $this->assertArrayHasKey('error', $data);
    }

    public function testChatEndpointCorsHeaders(): void
    {
        // Test middleware CORS en endpoint POST
        $this->mockChatService
            ->method('processMessage')
            ->willReturn([
                'response' => 'Test response',
                'timestamp' => date('c'),
                'model' => 'gemini-pro'
            ]);

        $response = $this->postJson('/chat', [
            'message' => 'Test message',
            'conversation_id' => null
        ]);

        $this->assertTrue($response->hasHeader('Access-Control-Allow-Origin'));
    }

    public function testChatEndpointOptionsRequest(): void
    {
        // Test preflight CORS for POST requests
        $request = $this->createRequest(
            'OPTIONS',
            '/chat',
            [
                'Origin' => 'http://localhost:3000',
                'Access-Control-Request-Method' => 'POST',
                'Access-Control-Request-Headers' => 'Content-Type'
            ]
        );

        $response = $this->runApp($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('Access-Control-Allow-Origin'));
        $this->assertTrue($response->hasHeader('Access-Control-Allow-Methods'));
        $this->assertTrue($response->hasHeader('Access-Control-Allow-Headers'));
    }

    public function testChatEndpointConversationContext(): void
    {
        // Test that conversation context is passed correctly
        $conversationId = 'conv-123-456';
        
        $this->mockChatService
            ->expects($this->once())
            ->method('processMessage')
            ->with(
                $this->equalTo('Continue conversation'),
                $this->equalTo($conversationId),
                $this->anything()
            )
            ->willReturn([
                'response' => 'Continued response',
                'timestamp' => date('c'),
                'model' => 'gemini-pro'
            ]);

        $response = $this->postJson('/chat', [
            'message' => 'Continue conversation',
            'conversation_id' => $conversationId
        ]);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testChatEndpointRateLimitingIntegration(): void
    {
        // Use in-memory storage for reliable testing
        $this->useInMemoryRateLimitStorage = true;
        
        // Configure rate limiting with very low limit for testing
        $this->testRateLimitConfig = [
            'max_requests' => 1, // Solo 1 request permitido
            'time_window' => 900
        ];
        
        // Use real services and reset container
        $this->useRealChatService = true;
        $this->reconfigureApp([]);
        
        // Get the in-memory storage and ensure it's clean
        $rateLimitService = $this->container->get(\ChatbotDemo\Services\RateLimitService::class);
        $reflection = new \ReflectionClass($rateLimitService);
        $storageProperty = $reflection->getProperty('storage');
        $storageProperty->setAccessible(true);
        $storage = $storageProperty->getValue($rateLimitService);
        
        if ($storage instanceof \ChatbotDemo\Tests\Fixtures\InMemoryRateLimitStorage) {
            $storage->clear(); // Clear any previous data
            $this->assertTrue($storage->isHealthy(), 'Rate limit storage should be healthy');
        } else {
            $this->fail('Expected InMemoryRateLimitStorage but got ' . get_class($storage));
        }

        // Don't configure mocks - use real services for this test

        // First request should work
        $response1 = $this->postJson('/chat', [
            'message' => 'First message',
            'conversation_id' => null
        ]);
        
        $this->assertEquals(200, $response1->getStatusCode());
        
        // Verify that the first request was logged in storage
        $this->assertEquals(1, $storage->getRequestsCount('127.0.0.1', time() - 900), 'First request should be logged');

        // Segunda request debe ser rate limited
        $response2 = $this->postJson('/chat', [
            'message' => 'Second message',
            'conversation_id' => null
        ]);

        // Debug if test fails
        if ($response2->getStatusCode() !== 429) {
            $this->fail(sprintf(
                "Expected 429 (rate limited), got %d. Response: %s. Storage stats: %s",
                $response2->getStatusCode(),
                (string) $response2->getBody(),
                json_encode($storage->getStats())
            ));
        }

        $this->assertEquals(429, $response2->getStatusCode());
        
        $data = $this->getJsonResponse($response2);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('límite', strtolower($data['error'])); // Spanish text
        
        // Cleanup storage after test
        $storage->clear();
    }

    protected function createTestConfig(): AppConfig
    {
        $config = parent::createTestConfig();
        
        // Override rate limit config for specific tests
        if (isset($this->testRateLimitConfig)) {
            $configArray = $config->toArray();
            $configArray['rate_limit'] = $this->testRateLimitConfig;
            return AppConfig::createFromArray($configArray);
        }
        
        return $config;
    }

    protected function createRateLimitService(\ChatbotDemo\Config\AppConfig $config, \Psr\Log\LoggerInterface $logger): \ChatbotDemo\Services\RateLimitService
    {
        if ($this->useInMemoryRateLimitStorage) {
            $storage = new \ChatbotDemo\Tests\Fixtures\InMemoryRateLimitStorage();
            return new \ChatbotDemo\Services\RateLimitService($config, $logger, $storage);
        }
        
        return parent::createRateLimitService($config, $logger);
    }
}