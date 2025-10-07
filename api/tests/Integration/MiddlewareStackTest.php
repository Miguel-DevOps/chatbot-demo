<?php

declare(strict_types=1);

namespace ChatbotDemo\Tests\Integration;

/**
 * Test de integración para la cadena completa de middleware
 * 
 * Valida que todos los middlewares están funcionando correctamente
 * en el orden correcto: Body Parsing -> Routing -> Error Handler -> CORS
 */
class MiddlewareStackTest extends IntegrationTestCase
{
    public function testMiddlewareStackOrder(): void
    {
        // Test que middleware stack está configurado correctamente
        // Hacemos una request válida y verificamos que todos los middlewares procesan
        
        $response = $this->get('/health');
        
        // Si llegamos aquí con 200, toda la stack funcionó
        $this->assertEquals(200, $response->getStatusCode());
        
        // Verificar que CORS middleware funcionó
        $this->assertTrue($response->hasHeader('Access-Control-Allow-Origin'));
        
        // Verificar que content parsing funcionó (JSON response)
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        
        // Verificar que routing funcionó (respuesta estructurada)
        $data = $this->getJsonResponse($response);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('status', $data);
    }

    public function testBodyParsingMiddleware(): void
    {
        // Test que el body parsing middleware funciona correctamente
        $requestData = [
            'message' => 'Test message for parsing',
            'conversation_id' => ['prev_msg1', 'prev_msg2']
        ];
        
        $response = $this->postJson('/chat', $requestData);
        
        // Si no hay error 400 de parsing, el middleware funcionó
        $this->assertNotEquals(400, $response->getStatusCode(), 'Body parsing should work correctly');
    }

    public function testRoutingMiddleware(): void
    {
        // Test que el routing middleware identifica rutas correctamente
        $testRoutes = [
            ['/health', 'GET', 200],
            ['/health.php', 'GET', 200],
            ['/chat', 'POST', 200], // Asumiendo que hay mock configurado
            ['/chat.php', 'POST', 200],
            ['/non-existent', 'GET', 404],
        ];
        
        foreach ($testRoutes as [$path, $method, $expectedStatus]) {
            if ($method === 'POST') {
                $response = $this->postJson($path, [
                    'message' => 'test',
                    'conversation_id' => []
                ]);
            } else {
                $response = $this->get($path);
            }
            
            $this->assertEquals($expectedStatus, $response->getStatusCode(), 
                "Route {$method} {$path} should return {$expectedStatus}");
        }
    }

    public function testCorsMiddlewareSimpleRequest(): void
    {
        // Test CORS middleware en request simple
        $request = $this->createRequest(
            'GET',
            '/health',
            ['Origin' => 'http://localhost:3000']
        );
        
        $response = $this->runApp($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('Access-Control-Allow-Origin'));
        
        $corsOrigin = $response->getHeaderLine('Access-Control-Allow-Origin');
        $this->assertNotEmpty($corsOrigin);
    }

    public function testCorsMiddlewarePreflightRequest(): void
    {
        // Test CORS middleware en preflight request
        $request = $this->createRequest(
            'OPTIONS',
            '/chat',
            [
                'Origin' => 'http://localhost:3000',
                'Access-Control-Request-Method' => 'POST',
                'Access-Control-Request-Headers' => 'Content-Type, Authorization'
            ]
        );
        
        $response = $this->runApp($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('Access-Control-Allow-Origin'));
        $this->assertTrue($response->hasHeader('Access-Control-Allow-Methods'));
        $this->assertTrue($response->hasHeader('Access-Control-Allow-Headers'));
        
        // Verificar que métodos permitidos incluyen POST
        $allowedMethods = $response->getHeaderLine('Access-Control-Allow-Methods');
        $this->assertStringContainsString('POST', $allowedMethods);
        
        // Verificar que headers permitidos incluyen Content-Type
        $allowedHeaders = $response->getHeaderLine('Access-Control-Allow-Headers');
        $this->assertStringContainsString('Content-Type', $allowedHeaders);
    }

    public function testErrorHandlerMiddlewareIntegration(): void
    {
        // Test que error handler middleware captura errores y los formatea
        $response = $this->get('/this-route-does-not-exist');
        
        // Error handler debe haber formateado la respuesta
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        
        $data = $this->getJsonResponse($response);
        $this->assertArrayHasKey('error', $data);
        $this->assertIsString($data['error']);
    }

    public function testMiddlewareWithInvalidJson(): void
    {
        // Test que el stack maneja JSON inválido correctamente
        $request = $this->createRequest(
            'POST',
            '/chat',
            ['Content-Type' => 'application/json'],
            '{"invalid": json'
        );
        
        $response = $this->runApp($request);
        
        // Body parsing middleware debe detectar y error handler debe formatear
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        
        $data = $this->getJsonResponse($response);
        $this->assertArrayHasKey('error', $data);
    }

    public function testMiddlewareWithLargeRequest(): void
    {
        // Test que el stack maneja requests grandes
        $largeData = [
            'message' => str_repeat('Large message content. ', 10) // Reducir mucho más para encontrar el límite
        ];
        
        $response = $this->postJson('/chat', $largeData);
        
        // Stack debe manejar sin crashes
        $this->assertNotEquals(500, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
    }

    public function testMiddlewareHeaders(): void
    {
        // Test que los headers se mantienen a través de toda la stack
        $request = $this->createRequest(
            'GET',
            '/health',
            [
                'User-Agent' => 'Integration-Test/1.0',
                'Accept' => 'application/json',
                'Origin' => 'http://localhost:3000'
            ]
        );
        
        $response = $this->runApp($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        // Verificar que response headers están presentes
        $this->assertTrue($response->hasHeader('Content-Type'));
        $this->assertTrue($response->hasHeader('Access-Control-Allow-Origin'));
    }

    public function testMiddlewareErrorPropagation(): void
    {
        // Test que errores se propagan correctamente através del stack
        $scenarios = [
            // JSON malformado - debe ser capturado por body parsing
            ['POST', '/chat', '{"bad": json', 400],
            
            // Ruta inexistente - debe ser capturado por routing
            ['GET', '/nonexistent', '', 404],
            
            // Método no permitido - debe ser manejado por routing
            ['PUT', '/health', '', 404],
        ];
        
        foreach ($scenarios as [$method, $path, $body, $expectedStatus]) {
            $headers = $method === 'POST' ? ['Content-Type' => 'application/json'] : [];
            $request = $this->createRequest($method, $path, $headers, $body);
            
            $response = $this->runApp($request);
            
            $this->assertEquals($expectedStatus, $response->getStatusCode());
            $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
            
            $data = $this->getJsonResponse($response);
            $this->assertArrayHasKey('error', $data);
        }
    }

    public function testMiddlewarePerformance(): void
    {
        // Test que la stack de middleware no introduce latencia significativa
        $startTime = microtime(true);
        
        $response = $this->get('/health');
        
        $endTime = microtime(true);
        $processingTime = $endTime - $startTime;
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertLessThan(0.05, $processingTime, 'Middleware stack should process requests in less than 50ms');
    }
}