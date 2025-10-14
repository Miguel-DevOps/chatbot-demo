<?php

declare(strict_types=1);

namespace ChatbotDemo\Tests\Unit\Services;

use ChatbotDemo\Services\ChatService;
use ChatbotDemo\Services\DemoAiClient;
use ChatbotDemo\Services\KnowledgeBaseService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\TracerInterface;

/**
 * Test for the AI Client abstraction in ChatService
 * Verifies that ChatService works with different AI provider implementations
 */
class ChatServiceAiAbstractionTest extends TestCase
{
    private ChatService $chatService;
    private DemoAiClient $aiClient;
    private KnowledgeBaseService $knowledgeService;
    private TracerInterface $tracer;

    protected function setUp(): void
    {
        // Create demo AI client
        $this->aiClient = new DemoAiClient();
        
        // Create tracer
        $this->tracer = Globals::tracerProvider()->getTracer('test', '1.0.0');
        
        // Mock knowledge base service
        $this->knowledgeService = $this->createMock(KnowledgeBaseService::class);
        $this->knowledgeService
            ->method('getKnowledgeBase')
            ->willReturn('Test knowledge base content');
        $this->knowledgeService
            ->method('addUserContext')
            ->willReturnCallback(function($knowledge, $userMessage) {
                return $knowledge . "\n\nUser: " . $userMessage;
            });

        // Create ChatService with AI abstraction
        $this->chatService = new ChatService(
            $this->aiClient,
            $this->knowledgeService,
            new NullLogger(),
            $this->tracer
        );
    }

    public function testChatServiceUsesAiAbstraction(): void
    {
        // Arrange
        $userMessage = "Hello, how are you?";
        $expectedResponse = "Demo AI response";
        
        // Act
        $result = $this->chatService->processMessage($userMessage);
        
        // Assert
        $this->assertTrue($result['success']);
        $this->assertStringContainsString($expectedResponse, $result['response']);
        $this->assertEquals('demo', $result['mode']);
        $this->assertArrayHasKey('timestamp', $result);
    }

    public function testChatServiceCanSwitchAiProviders(): void
    {
        // Arrange
        $customResponse = "Custom AI provider response";
        $this->aiClient->setResponse($customResponse);
        
        // Act
        $result = $this->chatService->processMessage("Test message");
        
        // Assert
        $this->assertTrue($result['success']);
        $this->assertStringContainsString($customResponse, $result['response']);
        $this->assertEquals('demo', $result['mode']); // Provider name
    }

    public function testChatServiceHandlesUnavailableAiProvider(): void
    {
        // Arrange
        $this->aiClient->setAvailable(false);
        
        // Act
        $result = $this->chatService->processMessage("Test message");
        
        // Assert
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('temporarily unavailable', $result['response']);
        $this->assertEquals('error', $result['mode']);
    }

    public function testAiProviderNameIsTracked(): void
    {
        // Act
        $result = $this->chatService->processMessage("Test message");
        
        // Assert
        $this->assertEquals('demo', $result['mode']);
        $this->assertEquals('demo', $this->aiClient->getProviderName());
    }
}