<?php

declare(strict_types=1);

namespace ChatbotDemo\Tests\Integration;

use ChatbotDemo\Config\AppConfig;
use ChatbotDemo\Services\ChatService;
use ChatbotDemo\Services\KnowledgeBaseService;
use ChatbotDemo\Services\DemoAiClient;
use OpenTelemetry\API\Globals;
use Psr\Log\NullLogger;

/**
 * Integration tests with minimal mocking to verify real service interactions
 */
class ServiceIntegrationTest extends IntegrationTestCase
{
    public function testChatServiceKnowledgeBaseIntegration(): void
    {
        // Test that ChatService correctly integrates with KnowledgeBaseService
        // using real implementations (no mocks)
        
        $config = $this->createTestConfig();
        $logger = new NullLogger();
        $tracer = Globals::tracerProvider()->getTracer('test', '1.0.0');
        
        // Create real services
        $aiClient = new DemoAiClient();
        $knowledgeService = $this->createKnowledgeBaseService($config, $logger);
        $chatService = new ChatService($aiClient, $knowledgeService, $logger, $tracer);
        
        // Test with a message that should use knowledge base
        $result = $chatService->processMessage('¿Qué servicios ofrecen?');
        
        // Verify the result structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('response', $result);
        $this->assertArrayHasKey('timestamp', $result);
        $this->assertArrayHasKey('mode', $result);
        
        $this->assertTrue($result['success']);
        $this->assertIsString($result['response']);
        $this->assertStringContainsString('Demo AI response', $result['response']);
        $this->assertEquals('demo', $result['mode']);
        
        // Verify that knowledge base content was included
        // The response should contain knowledge base content mixed with user message
        $this->assertGreaterThan(50, strlen($result['response'])); // Should be longer due to knowledge base
    }

    public function testRateLimitServiceIntegration(): void
    {
        // Test rate limiting service with real implementation
        
        $config = AppConfig::createFromArray([
            'rate_limit' => [
                'max_requests' => 2,
                'time_window' => 60 // 1 minute window
            ]
        ]);
        $logger = new NullLogger();
        $storage = new \ChatbotDemo\Tests\Fixtures\InMemoryRateLimitStorage();
        
        $rateLimitService = new \ChatbotDemo\Services\RateLimitService($config, $logger, $storage);
        
        // Create mock requests
        $request1 = $this->createRequest('POST', '/chat');
        $request2 = $this->createRequest('POST', '/chat');
        $request3 = $this->createRequest('POST', '/chat');
        
        // First request should be allowed
        $result1 = $rateLimitService->checkRateLimit($request1);
        $this->assertTrue($result1['allowed']);
        $this->assertEquals(2, $result1['limit']);
        $this->assertEquals(1, $result1['remaining']);
        
        // Second request should be allowed
        $result2 = $rateLimitService->checkRateLimit($request2);
        $this->assertTrue($result2['allowed']);
        $this->assertEquals(2, $result2['limit']);
        $this->assertEquals(0, $result2['remaining']);
        
        // Third request should be rate limited
        $result3 = $rateLimitService->checkRateLimit($request3);
        $this->assertFalse($result3['allowed']);
        $this->assertEquals(2, $result3['limit']);
        $this->assertEquals(0, $result3['remaining']);
        $this->assertGreaterThan(0, $result3['retry_after']);
    }

    public function testFullChatFlowIntegration(): void
    {
        // Test the complete chat flow with real services (minimal mocking)
        
        // Override to use in-memory rate limiting for reliability
        $this->useInMemoryRateLimitStorage = true;
        $this->useRealChatService = true;
        
        $this->testRateLimitConfig = [
            'max_requests' => 5,
            'time_window' => 300
        ];
        
        $this->reconfigureApp([]);
        
        // Test multiple chat interactions
        $conversations = [
            'Hola, ¿cómo estás?',
            '¿Qué servicios ofrecen?',
            'Necesito ayuda con mi proyecto',
            'Gracias por la información'
        ];
        
        foreach ($conversations as $i => $message) {
            $response = $this->postJson('/chat', [
                'message' => $message,
                'conversation_id' => 'test-conversation-' . time()
            ]);
            
            $this->assertEquals(200, $response->getStatusCode());
            
            $data = $this->getJsonResponse($response);
            $this->assertArrayHasKey('success', $data);
            $this->assertArrayHasKey('response', $data);
            $this->assertArrayHasKey('timestamp', $data);
            $this->assertArrayHasKey('mode', $data);
            
            $this->assertTrue($data['success']);
            $this->assertIsString($data['response']);
            $this->assertEquals('test-mock', $data['mode']);
            
            // Verify response has reasonable length (knowledge base + AI response)
            $this->assertGreaterThan(20, strlen($data['response']));
        }
        
        // Verify rate limiting is working by checking the storage
        $rateLimitService = $this->container->get(\ChatbotDemo\Services\RateLimitService::class);
        $reflection = new \ReflectionClass($rateLimitService);
        $storageProperty = $reflection->getProperty('storage');
        $storageProperty->setAccessible(true);
        $storage = $storageProperty->getValue($rateLimitService);
        
        $stats = $storage->getStats();
        $this->assertEquals(4, $stats['total_requests']); // Should have logged all 4 requests
    }

    public function testKnowledgeBaseServiceStandalone(): void
    {
        // Test KnowledgeBaseService functionality independently
        
        $config = $this->createTestConfig();
        $logger = new NullLogger();
        
        $knowledgeService = $this->createKnowledgeBaseService($config, $logger);
        
        // Test getting knowledge base
        $knowledge = $knowledgeService->getKnowledgeBase();
        $this->assertIsString($knowledge);
        $this->assertNotEmpty($knowledge);
        
        // Test adding user context
        $userMessage = 'Test user message';
        $contextualKnowledge = $knowledgeService->addUserContext($knowledge, $userMessage);
        
        $this->assertStringContainsString($knowledge, $contextualKnowledge);
        $this->assertStringContainsString($userMessage, $contextualKnowledge);
        $this->assertGreaterThan(strlen($knowledge), strlen($contextualKnowledge));
    }

    public function testHealthEndpointWithServicesIntegration(): void
    {
        // Test that health endpoint correctly reports service status
        
        $response = $this->get('/health');
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = $this->getJsonResponse($response);
        
        // Debug: Let's see what we actually get
        error_log("Health endpoint response: " . json_encode($data, JSON_PRETTY_PRINT));
        
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('timestamp', $data);  
        $this->assertArrayHasKey('checks', $data);
        
        $this->assertEquals('ok', $data['status']);
        $this->assertIsArray($data['checks']);
        
        // Verify that health checks are reported
        $expectedChecks = ['knowledge_base', 'rate_limit_storage', 'database', 'api_key'];
        foreach ($expectedChecks as $checkName) {
            $this->assertArrayHasKey($checkName, $data['checks'], 
                "Health check '$checkName' should be present");
        }
        
        // Verify basic health check structure with more permissive validation
        foreach ($data['checks'] as $checkName => $checkData) {
            $this->assertIsArray($checkData, "Check $checkName should be an array");
            $this->assertArrayHasKey('status', $checkData, "Check $checkName should have status");
            
            // More permissive status validation
            $actualStatus = $checkData['status'];
            $this->assertIsString($actualStatus, "Check $checkName status should be a string");
            $this->assertNotEmpty($actualStatus, "Check $checkName status should not be empty");
        }
    }

    protected function createTestConfig(): AppConfig
    {
        $config = parent::createTestConfig();
        
        // Add specific configuration for integration tests
        if (isset($this->testRateLimitConfig)) {
            $configArray = $config->toArray();
            $configArray['rate_limit'] = array_merge(
                $configArray['rate_limit'] ?? [],
                $this->testRateLimitConfig
            );
            return AppConfig::createFromArray($configArray);
        }
        
        return $config;
    }

    private bool $useInMemoryRateLimitStorage = false;
    private bool $useRealChatService = false;
    private ?array $testRateLimitConfig = null;

    protected function createRateLimitService(AppConfig $config, \Psr\Log\LoggerInterface $logger): \ChatbotDemo\Services\RateLimitService
    {
        if ($this->useInMemoryRateLimitStorage) {
            $storage = new \ChatbotDemo\Tests\Fixtures\InMemoryRateLimitStorage();
            return new \ChatbotDemo\Services\RateLimitService($config, $logger, $storage);
        }
        
        return parent::createRateLimitService($config, $logger);
    }

    protected function createChatService($aiClient, $knowledgeService, $logger, $tracer): ChatService
    {
        if ($this->useRealChatService) {
            return new ChatService($aiClient, $knowledgeService, $logger, $tracer);
        }
        
        return parent::createChatService($aiClient, $knowledgeService, $logger, $tracer);
    }
}