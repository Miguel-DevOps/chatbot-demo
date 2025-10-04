<?php

declare(strict_types=1);

namespace ChatbotDemo\Repositories;

use ChatbotDemo\Config\AppConfig;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Filesystem Knowledge Provider
 * 
 * Implementation of KnowledgeProviderInterface that reads knowledge
 * from markdown files in the filesystem.
 */
class FilesystemKnowledgeProvider implements KnowledgeProviderInterface
{
    private AppConfig $config;
    private LoggerInterface $logger;

    public function __construct(AppConfig $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function getKnowledge(): string
    {
        $this->logger->info('Loading knowledge base from filesystem');
        return $this->loadKnowledgeFromFiles();
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

            // Fallback to legacy PHP file if no .md files found
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

        // Load all .md files
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
}