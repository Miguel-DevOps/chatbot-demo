<?php
namespace ChatbotDemo\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Mockery;
use ChatbotDemo\Services\ChatService;
use ChatbotDemo\Services\KnowledgeBaseService;
use ChatbotDemo\Services\GenerativeAiClientInterface;
use ChatbotDemo\Services\TracingService;
use ChatbotDemo\Exceptions\ValidationException;
use OpenTelemetry\API\Trace\SpanInterface;
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
        $tracingService = Mockery::mock(TracingService::class);

        // Configure basic expectations
        $logger->shouldReceive('info')->byDefault();
        $logger->shouldReceive('debug')->byDefault();
        $logger->shouldReceive('warning')->byDefault();
        $logger->shouldReceive('error')->byDefault();

        $tracingService->shouldReceive('startSpan')->byDefault()->andReturn(Mockery::mock(SpanInterface::class));
        $tracingService->shouldReceive('finishSpan')->byDefault();
        $tracingService->shouldReceive('finishSpanWithError')->byDefault();
        $tracingService->shouldReceive('addAttribute')->byDefault()->andReturnSelf();
        $tracingService->shouldReceive('addSpanEvent')->byDefault();
        $tracingService->shouldReceive('recordException')->byDefault();

        $knowledgeService->shouldReceive('getKnowledgeBase')->byDefault()->andReturn('Test knowledge base content');
        $knowledgeService->shouldReceive('addUserContext')->byDefault()->andReturn('Test knowledge with context');

        $aiClient->shouldReceive('generateContent')->byDefault()->andReturn('Test AI response');
        $aiClient->shouldReceive('getProviderName')->byDefault()->andReturn('test');
        $aiClient->shouldReceive('isAvailable')->byDefault()->andReturn(true);

        return [$aiClient, $knowledgeService, $logger, $tracingService];
    }

    public function testProcessMessageInDemoMode(): void
    {
        [$aiClient, $knowledgeService, $logger, $tracingService] = $this->createBasicMocks();

        $service = new ChatService($aiClient, $knowledgeService, $logger, $tracingService);
        $result = $service->processMessage('What is React?');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertTrue($result['success']);
    }

    public function testProcessMessageWithEmptyInput(): void
    {
        [$aiClient, $knowledgeService, $logger, $tracingService] = $this->createBasicMocks();

        $service = new ChatService($aiClient, $knowledgeService, $logger, $tracingService);

        $this->expectException(ValidationException::class);
        $service->processMessage('');
    }

    public function testProcessMessageWithWhitespaceOnlyInput(): void
    {
        [$aiClient, $knowledgeService, $logger, $tracingService] = $this->createBasicMocks();

        $service = new ChatService($aiClient, $knowledgeService, $logger, $tracingService);

        $this->expectException(ValidationException::class);
        $service->processMessage('   ');
    }

    public function testProcessMessageWithTooLongInput(): void
    {
        [$aiClient, $knowledgeService, $logger, $tracingService] = $this->createBasicMocks();

        $service = new ChatService($aiClient, $knowledgeService, $logger, $tracingService);
        $longMessage = str_repeat('a', 10001);

        $this->expectException(ValidationException::class);
        $service->processMessage($longMessage);
    }

    public function testProcessMessageWithForbiddenContent(): void
    {
        [$aiClient, $knowledgeService, $logger, $tracingService] = $this->createBasicMocks();

        $service = new ChatService($aiClient, $knowledgeService, $logger, $tracingService);

        $this->expectException(ValidationException::class);
        $service->processMessage('Content with forbidden words: jailbreak hack exploit');
    }

    public function testProcessMessageValidationSuccess(): void
    {
        [$aiClient, $knowledgeService, $logger, $tracingService] = $this->createBasicMocks();

        $aiClient->shouldReceive('generateContent')
            ->once()
            ->andReturn('Valid AI response');

        $knowledgeService->shouldReceive('getKnowledgeBase')
            ->once()
            ->andReturn('Knowledge base content');

        $service = new ChatService($aiClient, $knowledgeService, $logger, $tracingService);
        $result = $service->processMessage('What is JavaScript?');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('response', $result);
    }
}
