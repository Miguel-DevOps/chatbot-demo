<?php

declare(strict_types=1);

namespace ChatbotDemo\Services;

use ChatbotDemo\Exceptions\ValidationException;
use Psr\Log\LoggerInterface;

/**
 * Chat Service
 * Handles chat message processing using generative AI providers
 * This service is provider-agnostic thanks to the GenerativeAiClientInterface abstraction
 */
class ChatService
{
    private GenerativeAiClientInterface $aiClient;
    private KnowledgeBaseService $knowledgeService;
    private LoggerInterface $logger;

    public function __construct(
        GenerativeAiClientInterface $aiClient,
        KnowledgeBaseService $knowledgeService,
        LoggerInterface $logger
    ) {
        $this->aiClient = $aiClient;
        $this->knowledgeService = $knowledgeService;
        $this->logger = $logger;
    }

    public function processMessage(string $userMessage): array
    {
        $startTime = microtime(true);
        
        $this->logger->info('Processing chat message', [
            'message_length' => strlen($userMessage),
            'message_preview' => substr($userMessage, 0, 100) . (strlen($userMessage) > 100 ? '...' : ''),
            'ai_provider' => $this->aiClient->getProviderName()
        ]);

        try {
            // Validate message
            $this->validateMessage($userMessage);

            // Check if AI client is available
            if (!$this->aiClient->isAvailable()) {
                $this->logger->warning('AI client is not available');
                return [
                    'success' => false,
                    'response' => 'AI service is temporarily unavailable. Please try again later.',
                    'timestamp' => date('c'),
                    'mode' => 'error'
                ];
            }

            // Process with AI provider
            $result = $this->processWithAI($userMessage);
            
            $processingTime = round((microtime(true) - $startTime) * 1000, 2);
            $this->logger->info('Chat message processed successfully', [
                'processing_time_ms' => $processingTime,
                'response_length' => strlen($result['response']),
                'ai_provider' => $this->aiClient->getProviderName()
            ]);
            
            return $result;

        } catch (\Exception $e) {
            $processingTime = round((microtime(true) - $startTime) * 1000, 2);
            $this->logger->error('Failed to process chat message', [
                'processing_time_ms' => $processingTime,
                'error' => $e->getMessage(),
                'exception_type' => get_class($e),
                'ai_provider' => $this->aiClient->getProviderName()
            ]);
            throw $e;
        }
    }

    private function validateMessage(string $message): void
    {
        $errors = [];
        
        if (empty(trim($message))) {
            $errors[] = 'Message cannot be empty';
        }

        if (strlen($message) > 500) {
            $errors[] = 'Message too long (maximum 500 characters)';
        }

        // Additional validation for inappropriate content (basic)
        $forbiddenWords = ['spam', 'hack', 'exploit'];
        $messageLower = strtolower($message);
        
        foreach ($forbiddenWords as $word) {
            if (strpos($messageLower, $word) !== false) {
                $errors[] = 'Forbidden content detected';
                break;
            }
        }

        if (!empty($errors)) {
            $this->logger->warning('Message validation failed', [
                'errors' => $errors,
                'message_length' => strlen($message)
            ]);
            throw new ValidationException('Invalid message', $errors);
        }

        $this->logger->debug('Message validation passed', [
            'message_length' => strlen($message)
        ]);
    }

    private function processWithAI(string $userMessage): array
    {
        try {
            // Get knowledge base and prepare full prompt
            $knowledge = $this->knowledgeService->getKnowledgeBase();
            $fullPrompt = $this->knowledgeService->addUserContext($knowledge, $userMessage);

            $this->logger->debug('Prepared prompt for AI', [
                'knowledge_base_length' => strlen($knowledge),
                'full_prompt_length' => strlen($fullPrompt),
                'ai_provider' => $this->aiClient->getProviderName()
            ]);

            // Generate content using the AI client
            $botResponse = $this->aiClient->generateContent($fullPrompt);

            return [
                'success' => true,
                'response' => $botResponse,
                'timestamp' => date('c'),
                'mode' => $this->aiClient->getProviderName()
            ];

        } catch (\Exception $e) {
            $this->logger->error('AI processing failed', [
                'error' => $e->getMessage(),
                'ai_provider' => $this->aiClient->getProviderName()
            ]);
            throw $e;
        }
    }
}