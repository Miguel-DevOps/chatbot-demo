<?php

declare(strict_types=1);

namespace ChatbotDemo\Repositories;

use ChatbotDemo\Config\AppConfig;
use Psr\Log\LoggerInterface;
use Redis;
use RedisException;

/**
 * Implementation of Rate Limiting storage using Redis
 *
 * This class demonstrates how the RateLimitStorageInterface abstraction
 * allows for easy implementation of different storage systems.
 *
 * To use this implementation:
 * 1. Install Redis: sudo apt-get install redis-server
 * 2. Install ext-redis: sudo apt-get install php-redis
 * 3. Modify DependencyContainer.php to use RedisRateLimitStorage
 *
 * NOTE: This is a sample implementation to demonstrate the architecture.
 * In production, consider using Predis or phpredis with more robust handling.
 */
class RedisRateLimitStorage implements RateLimitStorageInterface
{
    private AppConfig $config;
    private LoggerInterface $logger;
    private ?Redis $redis = null;
    private string $keyPrefix = 'rate_limit:';

    public function __construct(AppConfig $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->initializeRedis();
    }

    /**
     * Initialize the connection to Redis
     */
    private function initializeRedis(): void
    {
        try {
            if (!extension_loaded('redis')) {
                throw new \RuntimeException('Redis extension is not loaded');
            }

            $this->redis = new Redis();
            
            $host = $this->config->get('redis.host', '127.0.0.1');
            $port = $this->config->get('redis.port', 6379);
            $timeout = $this->config->get('redis.timeout', 5);
            $database = $this->config->get('redis.database', 0);
            
            $connected = $this->redis->connect($host, $port, $timeout);
            
            if (!$connected) {
                throw new \RuntimeException('Failed to connect to Redis');
            }
            
            if ($database > 0) {
                $this->redis->select($database);
            }
            
            $this->logger->info('Redis rate limit storage initialized successfully', [
                'host' => $host,
                'port' => $port,
                'database' => $database
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to initialize Redis rate limit storage', [
                'error' => $e->getMessage()
            ]);
            
            $this->redis = null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestsCount(string $ip, int $windowStart): int
    {
        if (!$this->redis) {
            return 0;
        }

        try {
            $key = $this->keyPrefix . $ip;

            // Use Sorted Set to store timestamps
            // Count elements with score >= windowStart
            $count = $this->redis->zCount($key, $windowStart, '+inf');
            
            $this->logger->debug('Retrieved requests count from Redis storage', [
                'ip' => $ip,
                'window_start' => $windowStart,
                'count' => $count,
                'key' => $key
            ]);
            
            return $count;
            
        } catch (RedisException $e) {
            $this->logger->error('Failed to get requests count from Redis storage', [
                'error' => $e->getMessage(),
                'ip' => $ip,
                'window_start' => $windowStart
            ]);
            
            return 0;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function logRequest(string $ip, int $timestamp): void
    {
        if (!$this->redis) {
            return;
        }

        try {
            $key = $this->keyPrefix . $ip;
            $timeWindow = $this->config->get('rate_limit.time_window', 900);

            // Pipeline for atomic operations
            $pipe = $this->redis->pipeline();

            // Add the timestamp to the sorted set
            $pipe->zAdd($key, $timestamp, $timestamp);

            // Set expiration for the key
            $pipe->expire($key, $timeWindow * 2); // Give extra margin

            // Execute pipeline
            $pipe->exec();
            
            $this->logger->debug('Request logged to Redis storage', [
                'ip' => $ip,
                'timestamp' => $timestamp,
                'key' => $key
            ]);
            
        } catch (RedisException $e) {
            $this->logger->error('Failed to log request to Redis storage', [
                'error' => $e->getMessage(),
                'ip' => $ip,
                'timestamp' => $timestamp
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function cleanupExpiredRequests(int $windowStart): int
    {
        if (!$this->redis) {
            return 0;
        }

        try {
            $pattern = $this->keyPrefix . '*';
            $keys = $this->redis->keys($pattern);
            $totalDeleted = 0;
            
            if (empty($keys)) {
                return 0;
            }
            
            foreach ($keys as $key) {
                // Remove elements with score < windowStart
                $deleted = $this->redis->zRemRangeByScore($key, '-inf', $windowStart - 1);
                $totalDeleted += $deleted;

                // If the set is empty, delete the key
                if ($this->redis->zCard($key) === 0) {
                    $this->redis->del($key);
                }
            }
            
            $this->logger->debug('Cleaned up expired requests from Redis storage', [
                'window_start' => $windowStart,
                'keys_processed' => count($keys),
                'total_deleted' => $totalDeleted
            ]);
            
            return $totalDeleted;
            
        } catch (RedisException $e) {
            $this->logger->error('Failed to cleanup expired requests from Redis storage', [
                'error' => $e->getMessage(),
                'window_start' => $windowStart
            ]);
            
            return 0;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isHealthy(): bool
    {
        if (!$this->redis) {
            return false;
        }

        try {
            // Test simple connection
            $result = $this->redis->ping();
            return $result === '+PONG' || $result === 'PONG';
            
        } catch (RedisException $e) {
            $this->logger->warning('Redis storage health check failed', [
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getStats(): array
    {
        if (!$this->redis) {
            return [
                'storage_type' => 'redis',
                'error' => 'Redis not available',
                'last_check' => time()
            ];
        }

        try {
            $info = $this->redis->info();
            $pattern = $this->keyPrefix . '*';
            $keys = $this->redis->keys($pattern);
            
            $totalRequests = 0;
            $uniqueIps = count($keys);
            // Count total requests
            foreach ($keys as $key) {
                $totalRequests += $this->redis->zCard($key);
            }

            // Count recent requests (last 24 hours)
            $yesterday = time() - 86400;
            $recentRequests = 0;
            foreach ($keys as $key) {
                $recentRequests += $this->redis->zCount($key, $yesterday, '+inf');
            }
            
            return [
                'storage_type' => 'redis',
                'redis_version' => $info['redis_version'] ?? 'unknown',
                'connected_clients' => $info['connected_clients'] ?? 0,
                'used_memory_human' => $info['used_memory_human'] ?? 'unknown',
                'total_keys' => $uniqueIps,
                'total_requests' => $totalRequests,
                'recent_requests_24h' => $recentRequests,
                'unique_ips' => $uniqueIps,
                'key_prefix' => $this->keyPrefix,
                'last_check' => time()
            ];
            
        } catch (RedisException $e) {
            $this->logger->error('Failed to get Redis storage stats', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'storage_type' => 'redis',
                'error' => 'Failed to retrieve stats: ' . $e->getMessage(),
                'last_check' => time()
            ];
        }
    }

    /**
     * Get the Redis instance (for special cases or testing)
     */
    public function getRedis(): ?Redis
    {
        return $this->redis;
    }

    /**
     * Close the Redis connection
     */
    public function __destruct()
    {
        if ($this->redis) {
            try {
                $this->redis->close();
            } catch (RedisException $e) {
                // Ignore errors on close
            }
        }
    }
}