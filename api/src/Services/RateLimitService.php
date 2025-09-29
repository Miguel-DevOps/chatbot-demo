<?php

declare(strict_types=1);

namespace ChatbotDemo\Services;

use ChatbotDemo\Config\AppConfig;
use ChatbotDemo\Exceptions\RateLimitException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use PDO;
use PDOException;

/**
 * Servicio de Rate Limiting
 * Implementa rate limiting robusto basado en IP con SQLite
 */
class RateLimitService
{
    private AppConfig $config;
    private LoggerInterface $logger;
    private ?PDO $pdo = null;

    public function __construct(AppConfig $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->initializeDatabase();
    }

    private function initializeDatabase(): void
    {
        $dbPath = $this->config->get('rate_limit.database_path');
        $dataDir = dirname($dbPath);
        
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
            $this->logger->info('Created rate limit database directory', ['path' => $dataDir]);
        }

        try {
            $this->pdo = new PDO("sqlite:" . $dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $sql = "CREATE TABLE IF NOT EXISTS rate_limits (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ip_address TEXT NOT NULL,
                request_time INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )";
            $this->pdo->exec($sql);
            
            $sql = "CREATE INDEX IF NOT EXISTS idx_ip_time ON rate_limits(ip_address, request_time)";
            $this->pdo->exec($sql);
            
            $this->logger->info('Rate limit database initialized successfully', ['path' => $dbPath]);
            
        } catch (PDOException $e) {
            $this->logger->error('Failed to initialize rate limit database', [
                'error' => $e->getMessage(),
                'path' => $dbPath
            ]);
        }
    }

    public function checkRateLimit(ServerRequestInterface $request): array
    {
        $ip = $this->getClientIP($request);
        $currentTime = time();
        $timeWindow = $this->config->get('rate_limit.time_window');
        $maxRequests = $this->config->get('rate_limit.max_requests');
        $windowStart = $currentTime - $timeWindow;

        try {
            // Limpiar requests antiguos
            $stmt = $this->pdo->prepare("DELETE FROM rate_limits WHERE request_time < ?");
            $stmt->execute([$windowStart]);

            // Contar requests actuales
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM rate_limits WHERE ip_address = ? AND request_time >= ?");
            $stmt->execute([$ip, $windowStart]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $currentRequests = $result['count'] ?? 0;
            $remaining = max(0, $maxRequests - $currentRequests);
            $allowed = $currentRequests < $maxRequests;

            $this->logger->debug('Rate limit check', [
                'ip' => $ip,
                'current_requests' => $currentRequests,
                'max_requests' => $maxRequests,
                'remaining' => $remaining,
                'allowed' => $allowed,
                'time_window' => $timeWindow
            ]);

            // Si está permitido, registrar el request
            if ($allowed) {
                $stmt = $this->pdo->prepare("INSERT INTO rate_limits (ip_address, request_time) VALUES (?, ?)");
                $stmt->execute([$ip, $currentTime]);
                $remaining = max(0, $remaining - 1);
                
                $this->logger->info('Request allowed', [
                    'ip' => $ip,
                    'remaining' => $remaining
                ]);
            } else {
                $this->logger->warning('Rate limit exceeded', [
                    'ip' => $ip,
                    'current_requests' => $currentRequests,
                    'max_requests' => $maxRequests,
                    'time_window' => $timeWindow
                ]);
            }

            return [
                'allowed' => $allowed,
                'limit' => $maxRequests,
                'remaining' => $remaining,
                'reset' => $currentTime + $timeWindow,
                'retry_after' => $timeWindow
            ];

        } catch (PDOException $e) {
            $this->logger->error('Rate limiting database error', [
                'error' => $e->getMessage(),
                'ip' => $ip
            ]);
            
            // Fail-open: permitir el request si hay error
            return [
                'allowed' => true,
                'limit' => $maxRequests,
                'remaining' => $maxRequests - 1,
                'reset' => $currentTime + $timeWindow,
                'retry_after' => 0
            ];
        }
    }

    public function enforceRateLimit(ServerRequestInterface $request): void
    {
        $result = $this->checkRateLimit($request);
        
        if (!$result['allowed']) {
            $ip = $this->getClientIP($request);
            
            $this->logger->warning('Rate limit enforced - request blocked', [
                'ip' => $ip,
                'limit' => $result['limit'],
                'retry_after' => $result['retry_after']
            ]);
            
            throw new RateLimitException(
                'Rate limit exceeded. Please try again later.',
                $result['retry_after']
            );
        }
    }

    private function getClientIP(ServerRequestInterface $request): string
    {
        $serverParams = $request->getServerParams();
        
        // Headers de proxies/balanceadores
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP'
        ];
        
        foreach ($headers as $header) {
            if (!empty($serverParams[$header])) {
                $ips = explode(',', $serverParams[$header]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $serverParams['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}