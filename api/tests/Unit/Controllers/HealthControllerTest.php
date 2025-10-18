<?php
namespace ChatbotDemo\Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;
use Mockery;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Slim\Psr7\Uri;
use Slim\Psr7\Headers;
use Slim\Psr7\Stream;
use ChatbotDemo\Controllers\HealthController;
use ChatbotDemo\Services\KnowledgeBaseService;
use ChatbotDemo\Services\RateLimitService;
use ChatbotDemo\Config\AppConfig;
use Psr\Log\LoggerInterface;

class HealthControllerTest extends TestCase
{
    private HealthController $healthController;
    private $mockConfig;
    private $mockLogger;
    private $mockRateLimitService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock configuration and logger
        $this->mockConfig = Mockery::mock(AppConfig::class);
        $this->mockLogger = Mockery::mock(LoggerInterface::class);
        $this->mockRateLimitService = Mockery::mock(RateLimitService::class);
        
        // Create HealthController instance with mocked dependencies
        $this->healthController = new HealthController($this->mockConfig, $this->mockLogger, $this->mockRateLimitService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testHealthCheckBasic(): void
    {
        // Arrange
        $uri = new Uri('http', 'localhost', null, '/health');
        $headers = new Headers();
        $stream = new Stream(fopen('php://memory', 'r+'));
        $request = new Request('GET', $uri, $headers, [], [], $stream);
        $response = new Response();
        
        // Basic configuration mock
        $this->mockConfig
            ->shouldReceive('get')
            ->andReturn('test-value');

        // Act
        $result = $this->healthController->health($request, $response);

        // Assert
        $this->assertEquals(200, $result->getStatusCode());
        
        $body = json_decode((string) $result->getBody(), true);
        $this->assertArrayHasKey('status', $body);
        $this->assertArrayHasKey('service', $body);
        $this->assertArrayHasKey('timestamp', $body);
    }

}