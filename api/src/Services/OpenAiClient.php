<?php

declare(strict_types=1);

namespace ChatbotDemo\Services;

use ChatbotDemo\Exceptions\ExternalServiceException;
use Psr\Log\LoggerInterface;

/**
 * OpenAI-like AI Client Implementation
 * 
 * Example implementation to demonstrate how easy it is to switch AI providers
 * This could be extended to actually call OpenAI's API
 */
class OpenAiClient implements GenerativeAiClientInterface
{
    private string $apiKey;
    private LoggerInterface $logger;

    public function __construct(string $apiKey, LoggerInterface $logger)
    {
        $this->apiKey = $apiKey;
        $this->logger = $logger;
    }

    public function generateContent(string $prompt): string
    {
        $this->logger->info('Calling OpenAI-like API', [
            'provider' => $this->getProviderName(),
            'prompt_length' => strlen($prompt)
        ]);

        // In a real implementation, this would make HTTP calls to OpenAI
        // For demo purposes, we'll simulate the response
        if ($this->apiKey === 'demo') {
            return $this->generateDemoResponse($prompt);
        }

        // This would be the actual OpenAI API call
        // return $this->callOpenAiAPI($prompt);
        
        throw new ExternalServiceException('OpenAI', 'OpenAI API not implemented in demo');
    }

    public function getProviderName(): string
    {
        return $this->apiKey === 'demo' ? 'openai-demo' : 'openai';
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    private function generateDemoResponse(string $prompt): string
    {
        $this->logger->info('Returning OpenAI demo response');
        
        // Simulate different response style than Gemini
        return "OpenAI-style response: I understand your request about '" . 
               substr($prompt, -100) . "'. This demonstrates provider abstraction working perfectly!";
    }

    // In a real implementation, this would contain the actual OpenAI API logic
    // private function callOpenAiAPI(string $prompt): string { ... }
}