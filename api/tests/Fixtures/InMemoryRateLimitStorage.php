<?php

declare(strict_types=1);

namespace ChatbotDemo\Tests\Fixtures;

use ChatbotDemo\Repositories\RateLimitStorageInterface;

/**
 * Simple in-memory rate limit storage for testing
 */
class InMemoryRateLimitStorage implements RateLimitStorageInterface
{
    private array $requests = [];
    private bool $healthy = true;

    public function getRequestsCount(string $ip, int $windowStart): int
    {
        $count = 0;
        foreach ($this->requests as $request) {
            if ($request['ip'] === $ip && $request['timestamp'] >= $windowStart) {
                $count++;
            }
        }
        return $count;
    }

    public function logRequest(string $ip, int $timestamp): void
    {
        $this->requests[] = [
            'ip' => $ip,
            'timestamp' => $timestamp
        ];
    }

    public function cleanupExpiredRequests(int $windowStart): int
    {
        $originalCount = count($this->requests);
        $this->requests = array_filter($this->requests, function($request) use ($windowStart) {
            return $request['timestamp'] >= $windowStart;
        });
        return $originalCount - count($this->requests);
    }

    public function isHealthy(): bool
    {
        return $this->healthy;
    }

    public function getStats(): array
    {
        return [
            'type' => 'in-memory',
            'total_requests' => count($this->requests),
            'healthy' => $this->healthy
        ];
    }

    public function setHealthy(bool $healthy): void
    {
        $this->healthy = $healthy;
    }

    public function clear(): void
    {
        $this->requests = [];
    }
}