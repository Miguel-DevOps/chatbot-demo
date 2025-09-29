<?php
namespace ChatbotDemo\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Mockery;
use ChatbotDemo\Services\RateLimitService;
use ChatbotDemo\Repositories\RateLimitRepository;
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
        
        // Crear instancia del RateLimitService con dependencias mockeadas
        $this->rateLimitService = new RateLimitService($this->mockConfig, $this->mockLogger);
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
        
        // Act & Assert - no debería lanzar excepción
        $this->rateLimitService->enforceRateLimit($request);
        $this->assertTrue(true); // Si llegamos aquí, no hubo excepción
    }
}