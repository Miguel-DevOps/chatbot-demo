<?php

declare(strict_types=1);

namespace ChatbotDemo\Services;

use ChatbotDemo\Exceptions\ExternalServiceException;

/**
 * Generative AI Client Interface
 * 
 * Abstraction for different generative AI providers (Gemini, OpenAI, Claude, etc.)
 * Allows the application to switch between different AI providers without changing business logic
 */
interface GenerativeAiClientInterface
{
    /**
     * Generate content using the AI model
     * 
     * @param string $prompt The prompt to send to the AI model
     * @return string The generated response from the AI
     * @throws ExternalServiceException When the AI service is unavailable or returns an error
     */
    public function generateContent(string $prompt): string;

    /**
     * Get the name of the AI provider
     * 
     * @return string The provider name (e.g., 'gemini', 'openai', 'demo')
     */
    public function getProviderName(): string;

    /**
     * Check if the AI client is available and properly configured
     * 
     * @return bool True if the client is ready to use
     */
    public function isAvailable(): bool;
}