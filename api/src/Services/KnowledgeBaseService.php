<?php

declare(strict_types=1);

namespace ChatbotDemo\Services;

use ChatbotDemo\Config\AppConfig;
use ChatbotDemo\Repositories\KnowledgeProviderInterface;
use Psr\Log\LoggerInterface;

/**
 * Knowledge Base Service
 * Handles loading and caching of the knowledge base
 */
class KnowledgeBaseService
{
    private AppConfig $config;
    private LoggerInterface $logger;
    private KnowledgeProviderInterface $knowledgeProvider;
    private ?string $cachedKnowledge = null;
    private int $cacheTime = 0;

    public function __construct(
        AppConfig $config, 
        LoggerInterface $logger,
        KnowledgeProviderInterface $knowledgeProvider
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->knowledgeProvider = $knowledgeProvider;
    }

    public function getKnowledgeBase(): string
    {
        $cacheEnabled = $this->config->get('knowledge_base.cache_enabled', true);
        $cacheTtl = $this->config->get('knowledge_base.cache_ttl', 3600);
        $currentTime = time();

        // Check if we have valid cache
        if ($cacheEnabled && $this->cachedKnowledge !== null && ($currentTime - $this->cacheTime) < $cacheTtl) {
            $this->logger->debug('Knowledge base served from cache', [
                'cache_age' => $currentTime - $this->cacheTime,
                'cache_ttl' => $cacheTtl
            ]);
            return $this->cachedKnowledge;
        }

        // Load knowledge base from provider
        $this->logger->info('Loading knowledge base from provider');
        $knowledge = $this->knowledgeProvider->getKnowledge();

        // Update cache
        if ($cacheEnabled) {
            $this->cachedKnowledge = $knowledge;
            $this->cacheTime = $currentTime;
            $this->logger->debug('Knowledge base cached', [
                'knowledge_length' => strlen($knowledge),
                'cache_time' => $currentTime
            ]);
        }

        return $knowledge;
    }

    public function invalidateCache(): void
    {
        $this->logger->info('Knowledge base cache invalidated');
        $this->cachedKnowledge = null;
        $this->cacheTime = 0;
    }

    public function addUserContext(string $knowledge, string $userMessage, ?string $conversationId = null): string
    {
        $contextualKnowledge = $knowledge;
        
        // Add conversation context if provided
        if ($conversationId !== null && !empty($conversationId)) {
            $contextualKnowledge .= "\n\nContext de conversación:\n";
            $contextualKnowledge .= sprintf("conversation ID: %s\n", $conversationId);
        }
        
        $contextualKnowledge .= "\n\nUser Question: " . $userMessage;
        
        $this->logger->debug('Added user context to knowledge base', [
            'original_length' => strlen($knowledge),
            'contextual_length' => strlen($contextualKnowledge),
            'user_message_length' => strlen($userMessage),
            'has_conversation_id' => $conversationId !== null
        ]);
        
        return $contextualKnowledge;
    }
}