<?php

declare(strict_types=1);

namespace ChatbotDemo\Services;

use ChatbotDemo\Config\OpenTelemetryBootstrap;
use ChatbotDemo\Exceptions\ValidationException;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;
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
    private TracerInterface $tracer;

    public function __construct(
        GenerativeAiClientInterface $aiClient,
        KnowledgeBaseService $knowledgeService,
        LoggerInterface $logger,
        TracerInterface $tracer
    ) {
        $this->aiClient = $aiClient;
        $this->knowledgeService = $knowledgeService;
        $this->logger = $logger;
        $this->tracer = $tracer;
    }

    public function processMessage(string $userMessage, ?string $conversationId = null, ?SpanInterface $parentSpan = null): array
    {
        $startTime = microtime(true);
        
        // Start enhanced tracing span for message processing
        $aiAttributes = OpenTelemetryBootstrap::createAiAttributes(
            $this->aiClient->getProviderName(),
            $userMessage
        );
        
        $span = $this->tracer->spanBuilder('chat_message_processing')
            ->setParent($parentSpan ? \OpenTelemetry\Context\Context::getCurrent()->withContextValue($parentSpan) : null)
            ->setAttributes(array_merge($aiAttributes, [
                'message_length' => strlen($userMessage),
                'has_conversation_id' => $conversationId !== null,
                'conversation_id' => $conversationId ?? 'none'
            ]))
            ->startSpan();
        
        $this->logger->info('Processing chat message', [
            'message_length' => strlen($userMessage),
            'message_preview' => substr($userMessage, 0, 100) . (strlen($userMessage) > 100 ? '...' : ''),
            'ai_provider' => $this->aiClient->getProviderName(),
            'has_conversation_id' => $conversationId !== null
        ]);

        try {
            // Validate message with enhanced tracing
            $span->addEvent('message_validation_start');
            $this->validateMessage($userMessage);
            $span->addEvent('message_validation_complete');

            // Check if AI client is available
            $span->addEvent('ai_client_availability_check');
            if (!$this->aiClient->isAvailable()) {
                $this->logger->warning('AI client is not available');
                $span->addEvent('ai_client_unavailable');
                
                $result = [
                    'success' => false,
                    'response' => 'AI service is temporarily unavailable. Please try again later.',
                    'timestamp' => date('c'),
                    'mode' => 'error'
                ];
                
                $span->setAttributes([
                    'ai_available' => false,
                    'response_mode' => 'error'
                ])->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR, 'AI client unavailable')
                  ->end();
                
                return $result;
            }

            // Process with AI provider
            $span->addEvent('ai_processing_start');
            $result = $this->processWithAI($userMessage, $conversationId, $span);
            $span->addEvent('ai_processing_complete', [
                'response_length' => strlen($result['response'] ?? ''),
                'response_mode' => $result['mode'] ?? 'unknown'
            ]);
            
            $processingTime = round((microtime(true) - $startTime) * 1000, 2);
            $this->logger->info('Chat message processed successfully', [
                'processing_time_ms' => $processingTime,
                'response_length' => strlen($result['response']),
                'ai_provider' => $this->aiClient->getProviderName()
            ]);
            
            $processingTime = round((microtime(true) - $startTime) * 1000, 2);
            
            $span->setAttributes([
                'processing_time_ms' => $processingTime,
                'response_length' => strlen($result['response']),
                'ai_provider' => $this->aiClient->getProviderName(),
                'success' => true,
                'response_mode' => $result['mode'] ?? 'unknown'
            ])->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_OK)
              ->end();
            
            return $result;

        } catch (\Exception $e) {
            $processingTime = round((microtime(true) - $startTime) * 1000, 2);
            $this->logger->error('Failed to process chat message', [
                'processing_time_ms' => $processingTime,
                'error' => $e->getMessage(),
                'exception_type' => get_class($e),
                'ai_provider' => $this->aiClient->getProviderName()
            ]);
            
            $span->recordException($e)
                 ->setAttributes([
                     'processing_time_ms' => $processingTime,
                     'ai_provider' => $this->aiClient->getProviderName(),
                     'error.type' => get_class($e),
                     'error.message' => $e->getMessage()
                 ])->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR, $e->getMessage())
                   ->end();
            
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

    private function processWithAI(string $userMessage, ?string $conversationId, SpanInterface $parentSpan): array
    {
        // Start enhanced AI processing span
        $span = $this->tracer->spanBuilder('ai_processing')
            ->setParent(\OpenTelemetry\Context\Context::getCurrent()->withContextValue($parentSpan))
            ->setAttributes([
                'ai.provider' => $this->aiClient->getProviderName(),
                'ai.message_length' => strlen($userMessage),
                'ai.has_conversation_context' => $conversationId !== null,
                'ai.conversation_id' => $conversationId ?? 'none'
            ])
            ->startSpan();

        try {
            // Get knowledge base with enhanced tracing
            $span->addEvent('knowledge_base_retrieval_start');
            $knowledge = $this->knowledgeService->getKnowledgeBase();
            $span->addEvent('knowledge_base_retrieval_complete', [
                'knowledge_base_length' => strlen($knowledge)
            ]);

            $span->addEvent('prompt_preparation_start');
            $fullPrompt = $this->knowledgeService->addUserContext($knowledge, $userMessage, $conversationId);
            $span->addEvent('prompt_preparation_complete', [
                'full_prompt_length' => strlen($fullPrompt),
                'context_added' => $conversationId !== null
            ]);

            $this->logger->debug('Prepared prompt for AI', [
                'knowledge_base_length' => strlen($knowledge),
                'full_prompt_length' => strlen($fullPrompt),
                'ai_provider' => $this->aiClient->getProviderName(),
                'has_conversation_context' => $conversationId !== null
            ]);

            // Generate content using the AI client with enhanced tracing
            $span->addEvent('ai_api_call_start', [
                'prompt_length' => strlen($fullPrompt)
            ]);
            $botResponse = $this->aiClient->generateContent($fullPrompt);
            $span->addEvent('ai_api_call_complete', [
                'response_length' => strlen($botResponse)
            ]);

            $result = [
                'success' => true,
                'response' => $botResponse,
                'timestamp' => date('c'),
                'mode' => $this->aiClient->getProviderName()
            ];

            $span->setAttributes([
                'ai.success' => true,
                'ai.response_length' => strlen($botResponse),
                'ai.prompt_tokens' => strlen($fullPrompt), // Approximation
                'ai.completion_tokens' => strlen($botResponse) // Approximation
            ])->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_OK)
              ->end();

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('AI processing failed', [
                'error' => $e->getMessage(),
                'ai_provider' => $this->aiClient->getProviderName()
            ]);
            
            $span->recordException($e)
                 ->setAttributes([
                     'ai.success' => false,
                     'ai.error.type' => get_class($e),
                     'ai.error.message' => $e->getMessage()
                 ])->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR, $e->getMessage())
                   ->end();
            throw $e;
        }
    }
}