<?php
namespace ChatbotDemo\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Mockery;
use ChatbotDemo\Services\ChatService;
use ChatbotDemo\Services\KnowledgeBaseService;
use ChatbotDemo\Services\GenerativeAiClientInterface;
use ChatbotDemo\Exceptions\ValidationException;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Globals;
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
        Mockery::close();
    }

    private function createBasicMocks(): array
    {
        $aiClient = Mockery::mock(GenerativeAiClientInterface::class);
        $knowledgeService = Mockery::mock(KnowledgeBaseService::class);
        $logger = Mockery::mock(LoggerInterface::class);
        $tracer = Mockery::mock(TracerInterface::class);

        // Configure basic expectations
        $logger->shouldReceive('info')->byDefault();
        $logger->shouldReceive('debug')->byDefault();
        $logger->shouldReceive('warning')->byDefault();
        $logger->shouldReceive('error')->byDefault();

        // Use a real tracer for the tests to avoid complex mocking
        $tracer = Globals::tracerProvider()->getTracer('test', '1.0.0');

        $knowledgeService->shouldReceive('getKnowledgeBase')->byDefault()->andReturn('Test knowledge base content');
        $knowledgeService->shouldReceive('addUserContext')->byDefault()->andReturn('Test knowledge with context');

        $aiClient->shouldReceive('generateContent')->byDefault()->andReturn('Test AI response');
        $aiClient->shouldReceive('getProviderName')->byDefault()->andReturn('test');
        $aiClient->shouldReceive('isAvailable')->byDefault()->andReturn(true);

        return [$aiClient, $knowledgeService, $logger, $tracer];
    }

    public function testProcessMessageInDemoMode(): void
    {
        [$aiClient, $knowledgeService, $logger, $tracer] = $this->createBasicMocks();

        $service = new ChatService($aiClient, $knowledgeService, $logger, $tracer);
        $result = $service->processMessage('What is React?');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertTrue($result['success']);
    }

    public function testProcessMessageWithEmptyInput(): void
    {
        [$aiClient, $knowledgeService, $logger, $tracer] = $this->createBasicMocks();

        $service = new ChatService($aiClient, $knowledgeService, $logger, $tracer);

        $this->expectException(ValidationException::class);
        $service->processMessage('');
    }

    public function testProcessMessageWithWhitespaceOnlyInput(): void
    {
        [$aiClient, $knowledgeService, $logger, $tracer] = $this->createBasicMocks();

        $service = new ChatService($aiClient, $knowledgeService, $logger, $tracer);

        $this->expectException(ValidationException::class);
        $service->processMessage('   ');
    }

    public function testProcessMessageWithTooLongInput(): void
    {
        [$aiClient, $knowledgeService, $logger, $tracer] = $this->createBasicMocks();

        $service = new ChatService($aiClient, $knowledgeService, $logger, $tracer);
        $longMessage = str_repeat('a', 10001);

        $this->expectException(ValidationException::class);
        $service->processMessage($longMessage);
    }

    public function testProcessMessageWithForbiddenContent(): void
    {
        [$aiClient, $knowledgeService, $logger, $tracer] = $this->createBasicMocks();

        $service = new ChatService($aiClient, $knowledgeService, $logger, $tracer);

        $this->expectException(ValidationException::class);
        $service->processMessage('Content with forbidden words: jailbreak hack exploit');
    }

    public function testProcessMessageValidationSuccess(): void
    {
        [$aiClient, $knowledgeService, $logger, $tracer] = $this->createBasicMocks();

        $aiClient->shouldReceive('generateContent')
            ->once()
            ->andReturn('Valid AI response');

        $knowledgeService->shouldReceive('getKnowledgeBase')
            ->once()
            ->andReturn('Knowledge base content');

        $service = new ChatService($aiClient, $knowledgeService, $logger, $tracer);
        $result = $service->processMessage('What is JavaScript?');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('response', $result);
    }
}
