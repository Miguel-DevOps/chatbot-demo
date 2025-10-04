<?php

declare(strict_types=1);

namespace ChatbotDemo\Controllers;

use ChatbotDemo\Config\AppConfig;
use ChatbotDemo\Services\RateLimitService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

/**
 * Controlador de Health Check
 * Proporciona información sobre el estado de la API
 */
class HealthController
{
    private AppConfig $config;
    private LoggerInterface $logger;
    private RateLimitService $rateLimitService;

    public function __construct(
        AppConfig $config, 
        LoggerInterface $logger,
        RateLimitService $rateLimitService
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->rateLimitService = $rateLimitService;
    }

    public function health(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        
        // Respuesta simple para herramientas de monitoreo
        if (isset($queryParams['plain'])) {
            $response->getBody()->write('OK');
            return $response->withHeader('Content-Type', 'text/plain');
        }

        // Respuesta detallada
        $healthData = [
            'status' => 'ok',
            'service' => $this->config->get('app.name'),
            'version' => $this->config->get('app.version'),
            'environment' => $this->config->get('app.environment'),
            'timestamp' => date('c'),
            'uptime' => $this->getUptime(),
            'checks' => [
                'database' => $this->checkDatabase(),
                'rate_limit_storage' => $this->checkRateLimitStorage(),
                'knowledge_base' => $this->checkKnowledgeBase(),
                'api_key' => $this->checkApiKey()
            ]
        ];

        $response->getBody()->write(json_encode($healthData, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function getUptime(): string
    {
        $uptime = time() - $_SERVER['REQUEST_TIME'];
        return gmdate('H:i:s', $uptime);
    }

    private function checkRateLimitStorage(): array
    {
        try {
            $isHealthy = $this->rateLimitService->getStorageHealth();
            $stats = $this->rateLimitService->getStorageStats();
            
            return [
                'status' => $isHealthy ? 'ok' : 'error',
                'healthy' => $isHealthy,
                'stats' => $stats
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'healthy' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function checkDatabase(): array
    {
        try {
            $dbPath = $this->config->get('rate_limit.database_path');
            $isAccessible = is_file($dbPath) && is_readable($dbPath) && is_writable($dbPath);
            
            return [
                'status' => $isAccessible ? 'ok' : 'warning',
                'path' => $dbPath,
                'exists' => file_exists($dbPath),
                'writable' => is_writable(dirname($dbPath))
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    private function checkKnowledgeBase(): array
    {
        try {
            $knowledgePath = $this->config->get('knowledge_base.path');
            $files = glob($knowledgePath . '/*.md');
            
            return [
                'status' => !empty($files) ? 'ok' : 'warning',
                'path' => $knowledgePath,
                'files_count' => count($files),
                'files' => array_map('basename', $files)
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    private function checkApiKey(): array
    {
        try {
            $apiKey = $this->config->getGeminiApiKey();
            
            return [
                'status' => $apiKey === 'DEMO_MODE' ? 'demo' : 'ok',
                'mode' => $apiKey === 'DEMO_MODE' ? 'demo' : 'production',
                'configured' => $apiKey !== 'DEMO_MODE'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => 'API key not configured'
            ];
        }
    }
}