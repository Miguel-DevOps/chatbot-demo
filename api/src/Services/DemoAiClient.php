<?php

declare(strict_types=1);

namespace ChatbotDemo\Services;

/**
 * Demo AI Client Implementation
 * 
 * Mock implementation for testing and demo purposes
 * Implements the GenerativeAiClientInterface without making external API calls
 */
class DemoAiClient implements GenerativeAiClientInterface
{
    private string $response;
    private bool $available;

    public function __construct(string $response = 'Demo AI response', bool $available = true)
    {
        $this->response = $response;
        $this->available = $available;
    }

    public function generateContent(string $prompt): string
    {
        if (!$this->available) {
            throw new \ChatbotDemo\Exceptions\ExternalServiceException('Demo', 'Demo AI service unavailable');
        }

        return $this->response . ' (processed prompt: ' . substr($prompt, 0, 50) . '...)';
    }

    public function getProviderName(): string
    {
        return 'demo';
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function setResponse(string $response): void
    {
        $this->response = $response;
    }

    public function setAvailable(bool $available): void
    {
        $this->available = $available;
    }
}