<?php

declare(strict_types=1);

namespace ChatbotDemo\Tests\Unit\Services;

use ChatbotDemo\Config\AppConfig;
use ChatbotDemo\Repositories\RateLimitStorageInterface;
use ChatbotDemo\Services\RateLimitService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Test demonstrating the abstraction of Rate Limiting storage
 *
 * This test verifies that the RateLimitService can work with different
 * storage implementations without changes to its code.
 */
class RateLimitStorageAbstractionTest extends TestCase
{
    private AppConfig $config;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->config = $this->createMock(AppConfig::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        
        // Configurar valores por defecto
        $this->config->method('get')
            ->willReturnMap([
                ['rate_limit.max_requests', null, 10],
                ['rate_limit.time_window', null, 60],
            ]);
    }

    /**
     * Test demonstrating that RateLimitService works with any implementation
     * of RateLimitStorageInterface
     */
    public function testRateLimitServiceWorksWithAnyStorageImplementation(): void
    {
        // Create a storage mock
        $mockStorage = $this->createMock(RateLimitStorageInterface::class);

        // Configure the mock to simulate 5 existing requests
        $mockStorage->method('isHealthy')->willReturn(true);
        $mockStorage->method('cleanupExpiredRequests')->willReturn(0);
        $mockStorage->method('getRequestsCount')->willReturn(5);
        $mockStorage->expects($this->once())->method('logRequest');

        // Create the service with the mock
        $service = new RateLimitService($this->config, $this->logger, $mockStorage);

        // Create a request mock
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getServerParams')->willReturn(['REMOTE_ADDR' => '192.168.1.1']);

        // Execute the test
        $result = $service->checkRateLimit($request);

        // Verify that the service works correctly
        $this->assertTrue($result['allowed']);
        $this->assertEquals(10, $result['limit']);
        $this->assertEquals(4, $result['remaining']); // 10 - 5 - 1 = 4
    }

    /**
     * Test demonstrating the behavior when storage fails
     */
    public function testRateLimitServiceFailsOpenWhenStorageIsUnhealthy(): void
    {
        // Create a storage mock that fails
        $mockStorage = $this->createMock(RateLimitStorageInterface::class);
        $mockStorage->method('isHealthy')->willReturn(false);
        $mockStorage->method('getStats')->willReturn(['error' => 'Storage failed']);

        // Create the service with the mock
        $service = new RateLimitService($this->config, $this->logger, $mockStorage);

        // Create a request mock
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getServerParams')->willReturn(['REMOTE_ADDR' => '192.168.1.1']);

        // Execute the test
        $result = $service->checkRateLimit($request);

        // Verify that the service fails open (allows the request)
        $this->assertTrue($result['allowed']);
        $this->assertEquals(0, $result['retry_after']);
    }

    /**
     * Test demonstrating that the implementation can be easily swapped
     */
    public function testEasyStorageImplementationSwapping(): void
    {
        // Mock implementation "Redis"
        $redisStorage = new class implements RateLimitStorageInterface {
            private array $data = [];
            
            public function getRequestsCount(string $ip, int $windowStart): int {
                return count($this->data[$ip] ?? []);
            }
            
            public function logRequest(string $ip, int $timestamp): void {
                $this->data[$ip][] = $timestamp;
            }
            
            public function cleanupExpiredRequests(int $windowStart): int {
                return 0; // No-op for this test
            }
            
            public function isHealthy(): bool {
                return true;
            }
            
            public function getStats(): array {
                return ['storage_type' => 'redis_mock', 'total_ips' => count($this->data)];
            }
        };
        
        // Mock implementation "Memory"
        $memoryStorage = new class implements RateLimitStorageInterface {
            private array $requests = [];
            
            public function getRequestsCount(string $ip, int $windowStart): int {
                return count($this->requests[$ip] ?? []);
            }
            
            public function logRequest(string $ip, int $timestamp): void {
                $this->requests[$ip][] = $timestamp;
            }
            
            public function cleanupExpiredRequests(int $windowStart): int {
                return 0; // No-op for this test
            }
            
            public function isHealthy(): bool {
                return true;
            }
            
            public function getStats(): array {
                return ['storage_type' => 'memory_mock', 'total_requests' => array_sum(array_map('count', $this->requests))];
            }
        };
        
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getServerParams')->willReturn(['REMOTE_ADDR' => '192.168.1.1']);
        
        // Test with "Redis"
        $redisService = new RateLimitService($this->config, $this->logger, $redisStorage);
        $redisResult = $redisService->checkRateLimit($request);
        $redisStats = $redisService->getStorageStats();

        // Test with "Memory"
        $memoryService = new RateLimitService($this->config, $this->logger, $memoryStorage);
        $memoryResult = $memoryService->checkRateLimit($request);
        $memoryStats = $memoryService->getStorageStats();

        // Both services work the same regardless of storage
        $this->assertTrue($redisResult['allowed']);
        $this->assertTrue($memoryResult['allowed']);
        $this->assertEquals($redisResult['limit'], $memoryResult['limit']);

        // But each has its own statistics
        $this->assertEquals('redis_mock', $redisStats['storage_type']);
        $this->assertEquals('memory_mock', $memoryStats['storage_type']);
    }

    /**
     * Test verifying the correct exposure of storage methods
     */
    public function testStorageMethodExposure(): void
    {
        $mockStorage = $this->createMock(RateLimitStorageInterface::class);
        $mockStorage->method('isHealthy')->willReturn(true);
        $mockStorage->method('getStats')->willReturn(['test' => 'data']);
        
        $service = new RateLimitService($this->config, $this->logger, $mockStorage);

        // Verify that the service correctly exposes the storage methods
        $this->assertTrue($service->getStorageHealth());
        $this->assertEquals(['test' => 'data'], $service->getStorageStats());
        $this->assertSame($mockStorage, $service->getStorage());
    }
}