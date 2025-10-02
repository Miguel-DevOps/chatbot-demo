<?php

declare(strict_types=1);

namespace ChatbotDemo\Tests\Integration;

use ChatbotDemo\Config\AppConfig;
use ChatbotDemo\Services\ChatService;
use ChatbotDemo\Services\KnowledgeBaseService;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * Test de integración para endpoint de chat
 * 
 * Valida el flujo completo mientras mockea selectivamente dependencias externas.
 * Testing completo: Request -> Middleware -> Controller -> Service -> Response
 */
class ChatEndpointTest extends IntegrationTestCase
{
    private MockObject $mockChatService;

    protected function setUp(): void
    {
        // Crear mock del ChatService antes de setup
        $this->mockChatService = $this->createMock(ChatService::class);
        
        parent::setUp();
    }

    /**
     * Override del factory method para inyectar el mock
     */
    protected function createChatService(AppConfig $config, KnowledgeBaseService $knowledgeService, LoggerInterface $logger): ChatService
    {
        return $this->mockChatService;
    }

    public function testChatEndpointValidRequest(): void
    {
        // Arrange
        $expectedResponse = [
            'response' => 'Esta es una respuesta de prueba del chatbot.',
            'timestamp' => date('c'),
            'model' => 'gemini-pro'
        ];

        $this->mockChatService
            ->expects($this->once())
            ->method('processMessage')
            ->with(
                $this->equalTo('Hola, ¿cómo estás?')
            )
            ->willReturn($expectedResponse);

        // Act
        $response = $this->postJson('/chat', [
            'message' => 'Hola, ¿cómo estás?',
            'conversation_id' => []
        ]);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));

        $data = $this->getJsonResponse($response);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('response', $data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertEquals($expectedResponse['response'], $data['response']);
    }

    public function testChatEndpointWithPhpExtension(): void
    {
        // Test que funciona tanto /chat como /chat.php
        $this->mockChatService
            ->expects($this->once())
            ->method('processMessage')
            ->willReturn([
                'response' => 'Test response',
                'timestamp' => date('c'),
                'model' => 'gemini-pro'
            ]);

        $response = $this->postJson('/chat.php', [
            'message' => 'Test message',
            'conversation_id' => []
        ]);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testChatEndpointInvalidJsonRequest(): void
    {
        // Test manejo de JSON inválido por middleware
        $request = $this->createRequest(
            'POST',
            '/chat',
            ['Content-Type' => 'application/json'],
            '{"invalid": json}' // JSON malformado
        );

        $response = $this->runApp($request);

        // Error handling middleware debe manejar esto
        $this->assertEquals(400, $response->getStatusCode());
        
        $data = $this->getJsonResponse($response);
        $this->assertArrayHasKey('error', $data);
    }

    public function testChatEndpointMissingMessage(): void
    {
        // Test validación de parámetros requeridos
        $response = $this->postJson('/chat', [
            'conversation_id' => []
            // Falta 'message'
        ]);

        $this->assertEquals(400, $response->getStatusCode());
        
        $data = $this->getJsonResponse($response);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('message', strtolower($data['error']));
    }

    public function testChatEndpointEmptyMessage(): void
    {
        // Test validación de mensaje vacío
        $response = $this->postJson('/chat', [
            'message' => '',
            'conversation_id' => []
        ]);

        $this->assertEquals(400, $response->getStatusCode());
        
        $data = $this->getJsonResponse($response);
        $this->assertArrayHasKey('error', $data);
    }

    public function testChatEndpointInvalidMessageType(): void
    {
        // Test validación de tipo de mensaje
        $response = $this->postJson('/chat', [
            'message' => 123, // No es string
            'conversation_id' => []
        ]);

        $this->assertEquals(400, $response->getStatusCode());
        
        $data = $this->getJsonResponse($response);
        $this->assertArrayHasKey('error', $data);
    }

    public function testChatEndpointServiceException(): void
    {
        // Test manejo de excepciones del servicio
        $this->mockChatService
            ->expects($this->once())
            ->method('processMessage')
            ->willThrowException(new \Exception('Simulated service error'));

        $response = $this->postJson('/chat', [
            'message' => 'Test message',
            'conversation_id' => []
        ]);

        // Error handling middleware debe capturar y formatear
        $this->assertEquals(500, $response->getStatusCode());
        
        $data = $this->getJsonResponse($response);
        $this->assertArrayHasKey('error', $data);
    }

    public function testChatEndpointCorsHeaders(): void
    {
        // Test middleware CORS en endpoint POST
        $this->mockChatService
            ->method('processMessage')
            ->willReturn([
                'response' => 'Test response',
                'timestamp' => date('c'),
                'model' => 'gemini-pro'
            ]);

        $response = $this->postJson('/chat', [
            'message' => 'Test message',
            'conversation_id' => []
        ]);

        $this->assertTrue($response->hasHeader('Access-Control-Allow-Origin'));
    }

    public function testChatEndpointOptionsRequest(): void
    {
        // Test preflight CORS para POST requests
        $request = $this->createRequest(
            'OPTIONS',
            '/chat',
            [
                'Origin' => 'http://localhost:3000',
                'Access-Control-Request-Method' => 'POST',
                'Access-Control-Request-Headers' => 'Content-Type'
            ]
        );

        $response = $this->runApp($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('Access-Control-Allow-Origin'));
        $this->assertTrue($response->hasHeader('Access-Control-Allow-Methods'));
        $this->assertTrue($response->hasHeader('Access-Control-Allow-Headers'));
    }

    public function testChatEndpointConversationContext(): void
    {
        // Test que se pasa correctamente el contexto de conversación
        $conversationId = ['msg1', 'msg2'];
        
        $this->mockChatService
            ->expects($this->once())
            ->method('processMessage')
            ->with(
                $this->equalTo('Continue conversation')
            )
            ->willReturn([
                'response' => 'Continued response',
                'timestamp' => date('c'),
                'model' => 'gemini-pro'
            ]);

        $response = $this->postJson('/chat', [
            'message' => 'Continue conversation',
            'conversation_id' => $conversationId
        ]);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testChatEndpointRateLimitingIntegration(): void
    {
        // Test que el rate limiting está integrado (usando servicio real)
        // Nota: Este test usa el servicio real de rate limiting para validar integración
        
        // Crear configuración con límite muy bajo para testing
        $testConfig = [
            'rate_limit' => [
                'enabled' => true,
                'max_requests' => 1, // Solo 1 request permitido
                'time_window' => 900,
                'database_path' => '/tmp/test_rate_limit_integration.db'
            ]
        ];
        
        // Resetear container con nueva configuración
        $this->tearDown();
        $this->setUp();
        
        // Configurar mock para primera request
        $this->mockChatService
            ->expects($this->exactly(1))
            ->method('processMessage')
            ->willReturn([
                'response' => 'First response',
                'timestamp' => date('c'),
                'model' => 'gemini-pro'
            ]);

        // Primera request debe funcionar
        $response1 = $this->postJson('/chat', [
            'message' => 'First message',
            'conversation_id' => []
        ]);
        
        $this->assertEquals(200, $response1->getStatusCode());

        // Segunda request debe ser rate limited
        $response2 = $this->postJson('/chat', [
            'message' => 'Second message',
            'conversation_id' => []
        ]);
        
        $this->assertEquals(429, $response2->getStatusCode());
        
        $data = $this->getJsonResponse($response2);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('rate limit', strtolower($data['error']));
        
        // Cleanup
        @unlink('/tmp/test_rate_limit_integration.db');
    }

    protected function createTestConfig(): AppConfig
    {
        $config = parent::createTestConfig();
        
        // Override rate limit config for specific tests
        if (isset($this->testRateLimitConfig)) {
            $configArray = $config->toArray();
            $configArray['rate_limit'] = $this->testRateLimitConfig;
            return AppConfig::createFromArray($configArray);
        }
        
        return $config;
    }
}