<?php

declare(strict_types=1);

namespace ChatbotDemo\Services;

use ChatbotDemo\Exceptions\ValidationException;
use ChatbotDemo\Services\TracingService;
use OpenTelemetry\API\Trace\SpanInterface;
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
    private TracingService $tracingService;

    public function __construct(
        GenerativeAiClientInterface $aiClient,
        KnowledgeBaseService $knowledgeService,
        LoggerInterface $logger,
        TracingService $tracingService
    ) {
        $this->aiClient = $aiClient;
        $this->knowledgeService = $knowledgeService;
        $this->logger = $logger;
        $this->tracingService = $tracingService;
    }

        public function processMessage(string $userMessage, ?SpanInterface $parentSpan = null): array
    {
        $startTime = microtime(true);
        
        // Start tracing span for message processing
        $span = $this->tracingService->startSpan('chat_message_processing', [
            'message_length' => strlen($userMessage),
            'ai_provider' => $this->aiClient->getProviderName()
        ], $parentSpan);
        
        $this->logger->info('Processing chat message', [
            'message_length' => strlen($userMessage),
            'message_preview' => substr($userMessage, 0, 100) . (strlen($userMessage) > 100 ? '...' : ''),
            'ai_provider' => $this->aiClient->getProviderName()
        ]);

        try {
            // Validate message with tracing
            $this->tracingService->addSpanEvent($span, 'message_validation_start');
            $this->validateMessage($userMessage);
            $this->tracingService->addSpanEvent($span, 'message_validation_complete');

            // Check if AI client is available
            $this->tracingService->addSpanEvent($span, 'ai_client_availability_check');
            if (!$this->aiClient->isAvailable()) {
                $this->logger->warning('AI client is not available');
                $this->tracingService->addSpanEvent($span, 'ai_client_unavailable');
                
                $result = [
                    'success' => false,
                    'response' => 'AI service is temporarily unavailable. Please try again later.',
                    'timestamp' => date('c'),
                    'mode' => 'error'
                ];
                
                $this->tracingService->finishSpan($span, [
                    'ai_available' => false,
                    'response_mode' => 'error'
                ]);
                
                return $result;
            }

            // Process with AI provider
            $this->tracingService->addSpanEvent($span, 'ai_processing_start');
            $result = $this->processWithAI($userMessage, $span);
            $this->tracingService->addSpanEvent($span, 'ai_processing_complete');
            
            $processingTime = round((microtime(true) - $startTime) * 1000, 2);
            $this->logger->info('Chat message processed successfully', [
                'processing_time_ms' => $processingTime,
                'response_length' => strlen($result['response']),
                'ai_provider' => $this->aiClient->getProviderName()
            ]);
            
            $this->tracingService->finishSpan($span, [
                'processing_time_ms' => $processingTime,
                'response_length' => strlen($result['response']),
                'ai_provider' => $this->aiClient->getProviderName(),
                'success' => true
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
            
            $this->tracingService->finishSpanWithError($span, $e, [
                'processing_time_ms' => $processingTime,
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

    private function processWithAI(string $userMessage, SpanInterface $parentSpan): array
    {
        // Start AI processing span
        $span = $this->tracingService->startSpan('ai_processing', [
            'ai_provider' => $this->aiClient->getProviderName(),
            'message_length' => strlen($userMessage)
        ], $parentSpan);

        try {
            // Get knowledge base and prepare full prompt
            $this->tracingService->addSpanEvent($span, 'knowledge_base_retrieval_start');
            $knowledge = $this->knowledgeService->getKnowledgeBase();
            $this->tracingService->addSpanEvent($span, 'knowledge_base_retrieval_complete', [
                'knowledge_base_length' => strlen($knowledge)
            ]);

            $this->tracingService->addSpanEvent($span, 'prompt_preparation_start');
            $fullPrompt = $this->knowledgeService->addUserContext($knowledge, $userMessage);
            $this->tracingService->addSpanEvent($span, 'prompt_preparation_complete', [
                'full_prompt_length' => strlen($fullPrompt)
            ]);

            $this->logger->debug('Prepared prompt for AI', [
                'knowledge_base_length' => strlen($knowledge),
                'full_prompt_length' => strlen($fullPrompt),
                'ai_provider' => $this->aiClient->getProviderName()
            ]);

            // Generate content using the AI client
            $this->tracingService->addSpanEvent($span, 'ai_api_call_start');
            $botResponse = $this->aiClient->generateContent($fullPrompt);
            $this->tracingService->addSpanEvent($span, 'ai_api_call_complete', [
                'response_length' => strlen($botResponse)
            ]);

            $result = [
                'success' => true,
                'response' => $botResponse,
                'timestamp' => date('c'),
                'mode' => $this->aiClient->getProviderName()
            ];

            $this->tracingService->finishSpan($span, [
                'success' => true,
                'response_length' => strlen($botResponse)
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('AI processing failed', [
                'error' => $e->getMessage(),
                'ai_provider' => $this->aiClient->getProviderName()
            ]);
            
            $this->tracingService->finishSpanWithError($span, $e);
            throw $e;
        }
    }
}