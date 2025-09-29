<?php

declare(strict_types=1);

namespace ChatbotDemo\Services;

use ChatbotDemo\Config\AppConfig;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Servicio de Knowledge Base
 * Maneja la carga y caché de la base de conocimiento
 */
class KnowledgeBaseService
{
    private AppConfig $config;
    private LoggerInterface $logger;
    private ?string $cachedKnowledge = null;
    private int $cacheTime = 0;

    public function __construct(AppConfig $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function getKnowledgeBase(): string
    {
        $cacheEnabled = $this->config->get('knowledge_base.cache_enabled', true);
        $cacheTtl = $this->config->get('knowledge_base.cache_ttl', 3600);
        $currentTime = time();

        // Verificar si tenemos caché válido
        if ($cacheEnabled && $this->cachedKnowledge !== null && ($currentTime - $this->cacheTime) < $cacheTtl) {
            $this->logger->debug('Knowledge base served from cache', [
                'cache_age' => $currentTime - $this->cacheTime,
                'cache_ttl' => $cacheTtl
            ]);
            return $this->cachedKnowledge;
        }

        // Cargar knowledge base desde archivos
        $this->logger->info('Loading knowledge base from files');
        $knowledge = $this->loadKnowledgeFromFiles();
        
        // Actualizar caché
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

    private function loadKnowledgeFromFiles(): string
    {
        $knowledgePath = $this->config->get('knowledge_base.path');
        
        if (!is_dir($knowledgePath)) {
            $this->logger->error('Knowledge base directory not found', ['path' => $knowledgePath]);
            throw new RuntimeException("Knowledge base directory not found: {$knowledgePath}");
        }

        $knowledge = "";
        $files = glob($knowledgePath . '/*.md');
        
        if (empty($files)) {
            $this->logger->warning('No markdown files found, checking for legacy knowledge base');
            
            // Fallback al archivo PHP legacy si no hay archivos .md
            $legacyFile = dirname($knowledgePath) . '/knowledge-base.php';
            if (file_exists($legacyFile)) {
                $this->logger->info('Loading legacy knowledge base file', ['file' => $legacyFile]);
                return include $legacyFile;
            }
            
            $this->logger->error('No knowledge base files found', ['path' => $knowledgePath]);
            throw new RuntimeException("No knowledge base files found");
        }

        $this->logger->info('Loading knowledge base files', [
            'file_count' => count($files),
            'files' => array_map('basename', $files)
        ]);

        // Cargar todos los archivos .md
        $loadedFiles = 0;
        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content !== false) {
                $knowledge .= "\n\n" . $content;
                $loadedFiles++;
                $this->logger->debug('Loaded knowledge file', [
                    'file' => basename($file),
                    'size' => strlen($content)
                ]);
            } else {
                $this->logger->warning('Failed to read knowledge file', ['file' => $file]);
            }
        }

        $this->logger->info('Knowledge base loaded successfully', [
            'total_files' => count($files),
            'loaded_files' => $loadedFiles,
            'total_size' => strlen($knowledge)
        ]);

        return trim($knowledge);
    }

    public function invalidateCache(): void
    {
        $this->logger->info('Knowledge base cache invalidated');
        $this->cachedKnowledge = null;
        $this->cacheTime = 0;
    }

    public function addUserContext(string $knowledge, string $userMessage): string
    {
        $contextualKnowledge = $knowledge . "\n\nPregunta del usuario: " . $userMessage;
        
        $this->logger->debug('Added user context to knowledge base', [
            'original_length' => strlen($knowledge),
            'contextual_length' => strlen($contextualKnowledge),
            'user_message_length' => strlen($userMessage)
        ]);
        
        return $contextualKnowledge;
    }
}