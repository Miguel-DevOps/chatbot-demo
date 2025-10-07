<?php
namespace ChatbotDemo\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Mockery;
use ChatbotDemo\Services\RateLimitService;
use ChatbotDemo\Repositories\RateLimitRepository;
use ChatbotDemo\Repositories\RateLimitStorageInterface;
use ChatbotDemo\Config\AppConfig;
use Psr\Log\LoggerInterface;

class RateLimitServiceTest extends TestCase
{
    private RateLimitService $rateLimitService;
    private $mockConfig;
    private $mockLogger;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock del AppConfig
        $this->mockConfig = Mockery::mock(AppConfig::class);
        $this->mockConfig->shouldReceive('get')
            ->with('rate_limit.database_path')
            ->andReturn(':memory:'); // Base de datos en memoria para tests
        $this->mockConfig->shouldReceive('get')
            ->with('rate_limit.time_window')
            ->andReturn(3600); // 1 hora
        $this->mockConfig->shouldReceive('get')
            ->with('rate_limit.max_requests')
            ->andReturn(100); // 100 requests por hora
        
        // Mock del Logger
        $this->mockLogger = Mockery::mock(LoggerInterface::class);
        $this->mockLogger->shouldReceive('info')->byDefault();
        $this->mockLogger->shouldReceive('debug')->byDefault();
        $this->mockLogger->shouldReceive('warning')->byDefault();
        $this->mockLogger->shouldReceive('error')->byDefault();
        
        // Mock del storage
        $mockStorage = Mockery::mock(RateLimitStorageInterface::class);
        $mockStorage->shouldReceive('getRequestCount')->byDefault()->andReturn(0);
        $mockStorage->shouldReceive('getRequestsCount')->byDefault()->andReturn(0);
        $mockStorage->shouldReceive('incrementRequestCount')->byDefault();
        $mockStorage->shouldReceive('resetRequestCount')->byDefault();
        $mockStorage->shouldReceive('isHealthy')->byDefault()->andReturn(true);
        $mockStorage->shouldReceive('cleanupExpiredRequests')->byDefault();
        $mockStorage->shouldReceive('logRequest')->byDefault();
        
        // Crear instancia del RateLimitService con dependencias mockeadas
        $this->rateLimitService = new RateLimitService($this->mockConfig, $this->mockLogger, $mockStorage);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testCheckRateLimitWithinLimit(): void
    {
        // Arrange
        $request = Mockery::mock(\Psr\Http\Message\ServerRequestInterface::class);
        $request->shouldReceive('getServerParams')
            ->andReturn(['REMOTE_ADDR' => '192.168.1.1']);
        
        // Act
        $result = $this->rateLimitService->checkRateLimit($request);

        // Assert
        $this->assertTrue($result['allowed']);
        $this->assertEquals(100, $result['limit']);
        $this->assertGreaterThan(0, $result['remaining']);
    }

    public function testEnforceRateLimitSuccess(): void
    {
        // Arrange
        $request = Mockery::mock(\Psr\Http\Message\ServerRequestInterface::class);
        $request->shouldReceive('getServerParams')
            ->andReturn(['REMOTE_ADDR' => '192.168.1.2']);
        
        // Act & Assert - should not throw exception
        $this->rateLimitService->enforceRateLimit($request);
        $this->assertTrue(true); // Si llegamos aquí, no hubo excepción
    }
}