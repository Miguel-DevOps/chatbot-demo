<?php

declare(strict_types=1);

namespace ChatbotDemo\Services;

use ChatbotDemo\Config\AppConfig;
use ChatbotDemo\Exceptions\ExternalServiceException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Gemini AI Client Implementation
 * 
 * Handles communication with Google Gemini API
 * Implements the GenerativeAiClientInterface to allow easy switching between AI providers
 */
class GeminiApiClient implements GenerativeAiClientInterface
{
    private AppConfig $config;
    private LoggerInterface $logger;
    private string $apiKey;

    public function __construct(AppConfig $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->apiKey = $config->getGeminiApiKey();
    }

    public function generateContent(string $prompt): string
    {
        $this->logger->info('Calling Gemini API', [
            'provider' => $this->getProviderName(),
            'prompt_length' => strlen($prompt)
        ]);

        // If in demo mode, return demo response
        if ($this->apiKey === 'DEMO_MODE') {
            $this->logger->info('Returning demo response from Gemini client');
            return 'Demo response: the chatbot is working correctly. (Demo Mode)';
        }

        try {
            return $this->callGeminiAPI($prompt);
        } catch (ExternalServiceException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error in Gemini API call', [
                'error' => $e->getMessage(),
                'exception_type' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            throw new RuntimeException('AI service error: ' . $e->getMessage());
        }
    }

    public function getProviderName(): string
    {
        return $this->apiKey === 'DEMO_MODE' ? 'demo' : 'gemini';
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    private function callGeminiAPI(string $prompt): string
    {
        $this->logger->debug('Prepared prompt for Gemini', [
            'prompt_length' => strlen($prompt)
        ]);

        // Prepare data for Gemini
        $requestData = [
            'contents' => [[
                'parts' => [[
                    'text' => $prompt
                ]]
            ]],
            'generationConfig' => [
                'temperature' => $this->config->get('gemini.temperature', 0.7),
                'topK' => 1,
                'topP' => 1,
                'maxOutputTokens' => $this->config->get('gemini.max_tokens', 2048)
            ]
        ];

        // Configure cURL
        $ch = curl_init();
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->config->get('gemini.model')}:generateContent?key=" . $this->apiKey;
        $timeout = $this->config->get('gemini.timeout', 30);
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($requestData),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: ChatBot-Demo/2.0'
            ],
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true
        ]);

        $apiStartTime = microtime(true);
        $response = curl_exec($ch);
        $apiTime = round((microtime(true) - $apiStartTime) * 1000, 2);
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $this->logger->info('Gemini API response received', [
            'http_code' => $httpCode,
            'api_time_ms' => $apiTime,
            'response_size' => strlen($response),
            'has_curl_error' => !empty($curlError)
        ]);

        if ($curlError) {
            $this->logger->error('Gemini API connection error', ['curl_error' => $curlError]);
            throw new ExternalServiceException('Gemini', 'Connection error: ' . $curlError);
        }

        if ($httpCode !== 200) {
            $this->logger->error('Gemini API HTTP error', [
                'http_code' => $httpCode,
                'response' => substr($response, 0, 500)
            ]);
            throw new ExternalServiceException('Gemini', "Error in Gemini API: HTTP {$httpCode}");
        }

        $geminiResponse = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error('Gemini API invalid JSON response', [
                'json_error' => json_last_error_msg(),
                'response_preview' => substr($response, 0, 200)
            ]);
            throw new ExternalServiceException('Gemini', 'Invalid API response');
        }

        // Extract response
        $botResponse = $geminiResponse['candidates'][0]['content']['parts'][0]['text'] ?? 
                      'Sorry, I could not process your request at this time. Please try again later.';

        $this->logger->info('Gemini API call successful', [
            'response_length' => strlen($botResponse),
            'api_time_ms' => $apiTime,
            'provider' => $this->getProviderName()
        ]);

        return trim($botResponse);
    }
}