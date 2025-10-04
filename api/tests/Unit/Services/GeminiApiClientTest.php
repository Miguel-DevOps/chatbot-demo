<?php

declare(strict_types=1);

namespace ChatbotDemo\Tests\Unit\Services;

use ChatbotDemo\Config\AppConfig;
use ChatbotDemo\Services\GeminiApiClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Test for GeminiApiClient implementation
 * Verifies that GeminiApiClient implements the interface correctly
 */
class GeminiApiClientTest extends TestCase
{
    private GeminiApiClient $geminiClient;
    private AppConfig $config;

    protected function setUp(): void
    {
        // Create test configuration
        $this->config = AppConfig::createFromArray([
            'gemini' => [
                'api_key' => 'DEMO_MODE',
                'model' => 'gemini-pro',
                'timeout' => 30,
                'max_tokens' => 2048,
                'temperature' => 0.7
            ]
        ]);

        $this->geminiClient = new GeminiApiClient($this->config, new NullLogger());
    }

    public function testImplementsGenerativeAiClientInterface(): void
    {
        $this->assertInstanceOf(
            \ChatbotDemo\Services\GenerativeAiClientInterface::class,
            $this->geminiClient
        );
    }

    public function testGetProviderNameReturnsDemo(): void
    {
        $this->assertEquals('demo', $this->geminiClient->getProviderName());
    }

    public function testIsAvailableReturnsTrueWithApiKey(): void
    {
        $this->assertTrue($this->geminiClient->isAvailable());
    }

    public function testGenerateContentInDemoMode(): void
    {
        $prompt = "Test prompt for AI";
        $response = $this->geminiClient->generateContent($prompt);
        
        $this->assertIsString($response);
        $this->assertStringContainsString('Demo response', $response);
        $this->assertStringContainsString('Demo Mode', $response);
    }

    public function testGetProviderNameWithProductionKey(): void
    {
        // Create config with real API key
        $prodConfig = AppConfig::createFromArray([
            'gemini' => [
                'api_key' => 'real_api_key_123',
                'model' => 'gemini-pro',
                'timeout' => 30,
                'max_tokens' => 2048,
                'temperature' => 0.7
            ]
        ]);

        $prodClient = new GeminiApiClient($prodConfig, new NullLogger());
        
        $this->assertEquals('gemini', $prodClient->getProviderName());
    }

    public function testIsAvailableWithEmptyApiKey(): void
    {
        // Create config that simulates production environment without API key
        $emptyConfig = AppConfig::createFromArray([
            'app' => [
                'environment' => 'production' // This will cause getGeminiApiKey to throw exception instead of returning DEMO_MODE
            ],
            'gemini' => [
                'api_key' => '', // Empty API key
                'model' => 'gemini-pro',
                'timeout' => 30,
                'max_tokens' => 2048,
                'temperature' => 0.7
            ]
        ]);

        // This should throw a RuntimeException due to missing API key in production
        $this->expectException(\RuntimeException::class);
        $emptyClient = new GeminiApiClient($emptyConfig, new NullLogger());
    }
}