<?php

declare(strict_types=1);

namespace ChatbotDemo\Repositories;

/**
 * Knowledge Provider Interface
 * 
 * This interface defines the contract for knowledge providers.
 * It allows the system to be flexible in how knowledge is retrieved,
 * whether from filesystem, database, CMS, or any other source.
 */
interface KnowledgeProviderInterface
{
    /**
     * Get the knowledge base content
     * 
     * @return string The complete knowledge base content
     * @throws \RuntimeException When knowledge cannot be retrieved
     */
    public function getKnowledge(): string;
}