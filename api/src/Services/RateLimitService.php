<?php

declare(strict_types=1);

namespace ChatbotDemo\Services;

use ChatbotDemo\Config\AppConfig;
use ChatbotDemo\Exceptions\RateLimitException;
use ChatbotDemo\Repositories\RateLimitStorageInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

/**
 * Rate Limiting Service
 * Implements robust IP-based rate limiting using storage abstraction
 *
 * This implementation follows the dependency inversion principle,
 * allowing easy swapping between different storage systems
 * (SQLite, Redis, MySQL, etc.) without modifying business logic.
 */
class RateLimitService
{
    private AppConfig $config;
    private LoggerInterface $logger;
    private RateLimitStorageInterface $storage;

    public function __construct(
        AppConfig $config, 
        LoggerInterface $logger,
        RateLimitStorageInterface $storage
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->storage = $storage;
    }

    public function checkRateLimit(ServerRequestInterface $request): array
    {
        $ip = $this->getClientIP($request);
        $currentTime = time();
        $timeWindow = $this->config->get('rate_limit.time_window');
        $maxRequests = $this->config->get('rate_limit.max_requests');
        $windowStart = $currentTime - $timeWindow;

        // Check storage status
        if (!$this->storage->isHealthy()) {
            $this->logger->warning('Rate limit storage is unhealthy, failing open', [
                'ip' => $ip,
                'storage_stats' => $this->storage->getStats()
            ]);
            
            // Fail-open: allow the request if storage fails
            return [
                'allowed' => true,
                'limit' => $maxRequests,
                'remaining' => $maxRequests - 1,
                'reset' => $currentTime + $timeWindow,
                'retry_after' => 0
            ];
        }

        // Clean up old requests
        $cleanedCount = $this->storage->cleanupExpiredRequests($windowStart);
        if ($cleanedCount > 0) {
            $this->logger->debug('Cleaned up expired rate limit records', [
                'cleaned_count' => $cleanedCount,
                'window_start' => $windowStart
            ]);
        }

        // Count current requests
        $currentRequests = $this->storage->getRequestsCount($ip, $windowStart);
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

        // If allowed, log the request
        if ($allowed) {
            $this->storage->logRequest($ip, $currentTime);
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

    /**
     * Get the status of rate limiting storage
     */
    public function getStorageHealth(): bool
    {
        return $this->storage->isHealthy();
    }

    /**
     * Get statistics from storage
     */
    public function getStorageStats(): array
    {
        return $this->storage->getStats();
    }

    /**
     * Obtain the storage instance (useful for testing or special cases)
     */
    public function getStorage(): RateLimitStorageInterface
    {
        return $this->storage;
    }

    private function getClientIP(ServerRequestInterface $request): string
    {
        $serverParams = $request->getServerParams();

        // Proxy/load balancer headers
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