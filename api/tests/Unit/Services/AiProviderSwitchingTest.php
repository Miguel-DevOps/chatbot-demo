<?php

declare(strict_types=1);

namespace ChatbotDemo\Tests\Unit\Services;

use ChatbotDemo\Services\ChatService;
use ChatbotDemo\Services\DemoAiClient;
use ChatbotDemo\Services\OpenAiClient;
use ChatbotDemo\Services\KnowledgeBaseService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Test demonstrating the flexibility of AI provider abstraction
 * Shows how easy it is to switch between different AI providers
 */
class AiProviderSwitchingTest extends TestCase
{
    private KnowledgeBaseService $knowledgeService;

    protected function setUp(): void
    {
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
    }

    public function testSwitchingFromDemoToOpenAiProvider(): void
    {
        $userMessage = "Test switching AI providers";
        
        // Test with Demo AI
        $demoClient = new DemoAiClient();
        $chatServiceWithDemo = new ChatService($demoClient, $this->knowledgeService, new NullLogger());
        
        $demoResult = $chatServiceWithDemo->processMessage($userMessage);
        
        // Test with OpenAI-like client
        $openAiClient = new OpenAiClient('demo', new NullLogger());
        $chatServiceWithOpenAi = new ChatService($openAiClient, $this->knowledgeService, new NullLogger());
        
        $openAiResult = $chatServiceWithOpenAi->processMessage($userMessage);
        
        // Assert both work but with different providers
        $this->assertTrue($demoResult['success']);
        $this->assertTrue($openAiResult['success']);
        
        $this->assertEquals('demo', $demoResult['mode']);
        $this->assertEquals('openai-demo', $openAiResult['mode']);
        
        // Responses should be different (different AI providers)
        $this->assertNotEquals($demoResult['response'], $openAiResult['response']);
        $this->assertStringContainsString('Demo AI response', $demoResult['response']);
        $this->assertStringContainsString('OpenAI-style response', $openAiResult['response']);
    }

    public function testMultipleProvidersHaveDifferentCharacteristics(): void
    {
        $userMessage = "Hello world";
        
        // Demo AI Provider
        $demoClient = new DemoAiClient();
        $this->assertEquals('demo', $demoClient->getProviderName());
        $this->assertTrue($demoClient->isAvailable());
        
        // OpenAI-like Provider
        $openAiClient = new OpenAiClient('demo', new NullLogger());
        $this->assertEquals('openai-demo', $openAiClient->getProviderName());
        $this->assertTrue($openAiClient->isAvailable());
        
        // Different responses
        $demoResponse = $demoClient->generateContent($userMessage);
        $openAiResponse = $openAiClient->generateContent($userMessage);
        
        $this->assertNotEquals($demoResponse, $openAiResponse);
    }

    public function testChatServiceIsProviderAgnostic(): void
    {
        // The ChatService should work with any implementation of GenerativeAiClientInterface
        $providers = [
            'demo' => new DemoAiClient(),
            'openai' => new OpenAiClient('demo', new NullLogger())
        ];
        
        foreach ($providers as $providerName => $client) {
            $chatService = new ChatService($client, $this->knowledgeService, new NullLogger());
            $result = $chatService->processMessage("Test with {$providerName}");
            
            $this->assertTrue($result['success'], "Failed with provider: {$providerName}");
            $this->assertIsString($result['response']);
            $this->assertArrayHasKey('mode', $result);
            $this->assertArrayHasKey('timestamp', $result);
        }
    }
}