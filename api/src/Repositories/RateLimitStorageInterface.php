<?php

declare(strict_types=1);

namespace ChatbotDemo\Repositories;

/**
 * Interface for Rate Limiting Storage
 *
 * This abstraction allows easy swapping between different
 * storage systems (SQLite, Redis, MySQL, etc.) without
 * affecting the business logic of Rate Limiting.
 */
interface RateLimitStorageInterface
{
    /**
     * Get the number of requests made by an IP in the current time window
     *
     * @param string $ip Client IP address
     * @param int $windowStart Timestamp of the start of the time window
     * @return int Number of requests counted
     */
    public function getRequestsCount(string $ip, int $windowStart): int;

    /**
     * Log a new request for an IP
     *
     * @param string $ip Client IP address
     * @param int $timestamp Timestamp of the request
     * @return void
     */
    public function logRequest(string $ip, int $timestamp): void;

    /**
     * Clean up expired requests outside the time window
     *
     * @param int $windowStart Timestamp of the start of the time window
     * @return int Number of records deleted
     */
    public function cleanupExpiredRequests(int $windowStart): int;

    /**
     * Check if the storage is available and functioning
     *
     * @return bool True if the storage is healthy
     */
    public function isHealthy(): bool;

    /**
     * Get statistics from storage for monitoring
     *
     * @return array Storage statistics
     */
    public function getStats(): array;
}