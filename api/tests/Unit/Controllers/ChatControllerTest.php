<?php
namespace ChatbotDemo\Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;
use Mockery;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Slim\Psr7\Stream;
use Slim\Psr7\Uri;
use Slim\Psr7\Headers;
use ChatbotDemo\Controllers\ChatController;
use ChatbotDemo\Services\ChatService;
use ChatbotDemo\Services\RateLimitService;
use ChatbotDemo\Config\AppConfig;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Globals;
use Psr\Log\LoggerInterface;

class ChatControllerTest extends TestCase
{
    private ChatController $chatController;
    private $mockChatService;
    private $mockRateLimitService;
    private $mockConfig;
    private $mockLogger;
    private $mockTracer;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock de los servicios
        $this->mockChatService = Mockery::mock(ChatService::class);
        $this->mockRateLimitService = Mockery::mock(RateLimitService::class);
        $this->mockConfig = Mockery::mock(AppConfig::class);
        $this->mockLogger = Mockery::mock(LoggerInterface::class);
        $this->mockTracer = Mockery::mock(TracerInterface::class);
        
        // Configure basic expectations for config
        $this->mockConfig->shouldReceive('isDevelopment')->byDefault()->andReturn(false);
        $this->mockConfig->shouldReceive('getVersion')->byDefault()->andReturn('1.0.0-test');
        
        // Configure basic expectations for logger
        $this->mockLogger->shouldReceive('info')->byDefault();
        $this->mockLogger->shouldReceive('warning')->byDefault();
        $this->mockLogger->shouldReceive('error')->byDefault();
        $this->mockLogger->shouldReceive('debug')->byDefault();
        
        // Use a real tracer for the tests to avoid complex mocking
        $this->mockTracer = Globals::tracerProvider()->getTracer('test', '1.0.0');
        
        // Crear instancia del ChatController con dependencias mockeadas
        $this->chatController = new ChatController(
            $this->mockChatService,
            $this->mockRateLimitService,
            $this->mockConfig,
            $this->mockLogger,
            $this->mockTracer
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function createJsonRequest(array $data): Request
    {
        $json = json_encode($data);
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $json);
        rewind($stream);
        
        $streamObject = new Stream($stream);
        $uri = new Uri('http', 'localhost', null, '/chat');
        $headers = new Headers(['Content-Type' => 'application/json']);
        
        $request = new Request(
            'POST',
            $uri,
            $headers,
            [],
            [],
            $streamObject
        );
        
        // Set parsed body to simulate body parsing middleware
        return $request->withParsedBody($data);
    }

    public function testChatBasicSuccess(): void
    {
        // Arrange
        $message = "Hola, ¿cómo están?";
        $request = $this->createJsonRequest(['message' => $message]);
        $response = new Response();
        
        $expectedChatResponse = [
            'success' => true,
            'response' => 'Hola, estamos muy bien, gracias por preguntar.',
            'timestamp' => time()
        ];

        $rateLimitResult = [
            'allowed' => true,
            'remaining' => 99,
            'limit' => 100,
            'reset' => time() + 3600,
            'retry_after' => 0
        ];

        // Mock del rate limit service
        $this->mockRateLimitService
            ->shouldReceive('checkRateLimit')
            ->with($request)
            ->once()
            ->andReturn($rateLimitResult);

        // Mock del chat service
        $this->mockChatService
            ->shouldReceive('processMessage')
            ->with($message, null, Mockery::any())
            ->once()
            ->andReturn($expectedChatResponse);

        // Act
        $result = $this->chatController->chat($request, $response);

        // Assert
        $this->assertEquals(200, $result->getStatusCode());
        
        $body = json_decode((string) $result->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertEquals($expectedChatResponse['response'], $body['response']);
    }
}