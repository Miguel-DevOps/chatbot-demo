<?php
namespace ChatbotDemo\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Mockery;
use ChatbotDemo\Services\ChatService;
use ChatbotDemo\Services\KnowledgeBaseService;
use ChatbotDemo\Config\AppConfig;
use ChatbotDemo\Exceptions\ValidationException;
use Psr\Log\LoggerInterface;

class ChatServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mockery::resetContainer();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    public function testProcessMessageInDemoMode(): void
    {
        // Create mocks
        $config = Mockery::mock(AppConfig::class);
        $knowledgeService = Mockery::mock(KnowledgeBaseService::class);
        $logger = Mockery::mock(LoggerInterface::class);

        // Configure mock for demo mode
        $config->shouldReceive('getGeminiApiKey')
            ->once()
            ->andReturn('DEMO_MODE');

        // Configure logger expectations - permitir cualquier llamada
        $logger->shouldReceive('info')->byDefault();
        $logger->shouldReceive('debug')->byDefault();
        $logger->shouldReceive('warning')->byDefault();
        $logger->shouldReceive('error')->byDefault();

        // Create service
        $service = new ChatService($config, $knowledgeService, $logger);

        // Test with valid message in demo mode
        $result = $service->processMessage('What is React?');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('response', $result);
        $this->assertArrayHasKey('mode', $result);
        $this->assertEquals('demo', $result['mode']);
        $this->assertStringContainsString('Modo Demo', $result['response']);
    }

    public function testProcessMessageWithEmptyInput(): void
    {
        // Create mocks
        $config = Mockery::mock(AppConfig::class);
        $knowledgeService = Mockery::mock(KnowledgeBaseService::class);
        $logger = Mockery::mock(LoggerInterface::class);

        // Configure logger expectations - permitir cualquier llamada
        $logger->shouldReceive('info')->byDefault();
        $logger->shouldReceive('debug')->byDefault();
        $logger->shouldReceive('warning')->byDefault();
        $logger->shouldReceive('error')->byDefault();

        // Create service
        $service = new ChatService($config, $knowledgeService, $logger);

        // Expect ValidationException for empty message
        $this->expectException(ValidationException::class);

        // Test with empty message
        $service->processMessage('');
    }

    public function testProcessMessageWithWhitespaceOnlyInput(): void
    {
        // Create mocks
        $config = Mockery::mock(AppConfig::class);
        $knowledgeService = Mockery::mock(KnowledgeBaseService::class);
        $logger = Mockery::mock(LoggerInterface::class);

        // Configure logger expectations - permitir cualquier llamada
        $logger->shouldReceive('info')->byDefault();
        $logger->shouldReceive('debug')->byDefault();
        $logger->shouldReceive('warning')->byDefault();
        $logger->shouldReceive('error')->byDefault();

        // Create service
        $service = new ChatService($config, $knowledgeService, $logger);

        // Expect ValidationException for whitespace-only message
        $this->expectException(ValidationException::class);

        // Test with whitespace-only message
        $service->processMessage('   ');
    }

    public function testProcessMessageWithTooLongInput(): void
    {
        // Create mocks
        $config = Mockery::mock(AppConfig::class);
        $knowledgeService = Mockery::mock(KnowledgeBaseService::class);
        $logger = Mockery::mock(LoggerInterface::class);

        // Configure logger expectations - permitir cualquier llamada
        $logger->shouldReceive('info')->byDefault();
        $logger->shouldReceive('debug')->byDefault();
        $logger->shouldReceive('warning')->byDefault();
        $logger->shouldReceive('error')->byDefault();

        // Create service
        $service = new ChatService($config, $knowledgeService, $logger);

        // Expect ValidationException for long message
        $this->expectException(ValidationException::class);

        // Test with long message (over 1000 chars)
        $longMessage = str_repeat("a", 1001);
        $service->processMessage($longMessage);
    }

    public function testProcessMessageWithForbiddenContent(): void
    {
        // Create mocks
        $config = Mockery::mock(AppConfig::class);
        $knowledgeService = Mockery::mock(KnowledgeBaseService::class);
        $logger = Mockery::mock(LoggerInterface::class);

        // Configure logger expectations - permitir cualquier llamada
        $logger->shouldReceive('info')->byDefault();
        $logger->shouldReceive('debug')->byDefault();
        $logger->shouldReceive('warning')->byDefault();
        $logger->shouldReceive('error')->byDefault();

        // Create service
        $service = new ChatService($config, $knowledgeService, $logger);

        // Expect ValidationException for forbidden content
        $this->expectException(ValidationException::class);

        // Test with message containing forbidden word
        $service->processMessage('How to hack this system?');
    }

    public function testProcessMessageValidationSuccess(): void
    {
        // Create mocks
        $config = Mockery::mock(AppConfig::class);
        $knowledgeService = Mockery::mock(KnowledgeBaseService::class);
        $logger = Mockery::mock(LoggerInterface::class);

        // Configure mock for demo mode (to avoid API call)
        $config->shouldReceive('getGeminiApiKey')
            ->once()
            ->andReturn('DEMO_MODE');

        // Configure logger expectations - permitir cualquier llamada
        $logger->shouldReceive('info')->byDefault();
        $logger->shouldReceive('debug')->byDefault();
        $logger->shouldReceive('warning')->byDefault();
        $logger->shouldReceive('error')->byDefault();

        // Create service
        $service = new ChatService($config, $knowledgeService, $logger);

        // Test with valid message
        $result = $service->processMessage('Hello, how are you?');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('response', $result);
        $this->assertIsString($result['response']);
        $this->assertNotEmpty($result['response']);
    }
}
