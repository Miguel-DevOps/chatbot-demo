<?php

declare(strict_types=1);

namespace ChatbotDemo\Tests\Integration;

use ChatbotDemo\Config\AppConfig;
use ChatbotDemo\Config\DependencyContainer;
use ChatbotDemo\Controllers\ChatController;
use ChatbotDemo\Controllers\HealthController;
use ChatbotDemo\Middleware\CorsMiddleware;
use ChatbotDemo\Middleware\ErrorHandlerMiddleware;
use ChatbotDemo\Services\ChatService;
use ChatbotDemo\Services\KnowledgeBaseService;
use ChatbotDemo\Services\RateLimitService;
use DI\Container;
use DI\ContainerBuilder;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Factory\UriFactory;

/**
 * Clase base para tests de integración
 * 
 * Configura una aplicación Slim completa en memoria para testing,
 * permitiendo validar el flujo completo sin servidor HTTP externo.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected App $app;
    protected Container $container;
    protected RequestFactory $requestFactory;
    protected StreamFactory $streamFactory;
    protected UriFactory $uriFactory;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Reset container para cada test
        DependencyContainer::reset();
        
        // Configurar factores para crear requests/responses
        $this->requestFactory = new RequestFactory();
        $this->streamFactory = new StreamFactory();
        $this->uriFactory = new UriFactory();
        
        // Crear aplicación Slim en memoria
        $this->setupApplication();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        
        // Limpiar container después de cada test
        DependencyContainer::reset();
    }

    /**
     * Configura la aplicación Slim con DI container personalizado para testing
     */
    private function setupApplication(): void
    {
        // Crear container con configuración de testing
        $this->container = $this->buildTestContainer();
        
        // Configurar Slim con el container
        AppFactory::setContainer($this->container);
        $this->app = AppFactory::create();

        // Configurar middleware stack (mismo orden que en producción)
        $this->app->addBodyParsingMiddleware();
        $this->app->addRoutingMiddleware();
        
        // Middleware personalizado de manejo de errores
        $this->app->add($this->container->get(ErrorHandlerMiddleware::class));
        
        // Middleware CORS
        $this->app->add($this->container->get(CorsMiddleware::class));

        // Configurar rutas (mismo que en producción)
        $this->setupRoutes();
    }

    /**
     * Construye un container DI específico para testing
     */
    private function buildTestContainer(): Container
    {
        $builder = new ContainerBuilder();
        
        $builder->addDefinitions([
            // Configuración de testing
            AppConfig::class => function () {
                return $this->createTestConfig();
            },

            // Logger silencioso para testing
            LoggerInterface::class => function () {
                $logger = new Logger('test');
                $logger->pushHandler(new NullHandler()); // No output durante tests
                return $logger;
            },

            // Servicios - pueden ser sobrescritos en tests específicos
            KnowledgeBaseService::class => function (AppConfig $config, LoggerInterface $logger) {
                return $this->createKnowledgeBaseService($config, $logger);
            },

            RateLimitService::class => function (AppConfig $config, LoggerInterface $logger) {
                return $this->createRateLimitService($config, $logger);
            },

            ChatService::class => function (
                AppConfig $config,
                KnowledgeBaseService $knowledgeService,
                LoggerInterface $logger
            ) {
                return $this->createChatService($config, $knowledgeService, $logger);
            },

            // Controllers con autowiring
            ChatController::class => function (
                ChatService $chatService,
                RateLimitService $rateLimitService,
                AppConfig $config,
                LoggerInterface $logger
            ) {
                return new ChatController($chatService, $rateLimitService, $config, $logger);
            },

            HealthController::class => function (AppConfig $config, LoggerInterface $logger) {
                return new HealthController($config, $logger);
            },

            // Middleware
            CorsMiddleware::class => function (AppConfig $config, LoggerInterface $logger) {
                return new CorsMiddleware($config, $logger);
            },

            ErrorHandlerMiddleware::class => function (LoggerInterface $logger, AppConfig $config) {
                return new ErrorHandlerMiddleware($logger, $config);
            }
        ]);

        return $builder->build();
    }

    /**
     * Configura las rutas de la aplicación
     */
    private function setupRoutes(): void
    {
        // Rutas principales
        $this->app->get('/health.php', [HealthController::class, 'health']);
        $this->app->get('/health', [HealthController::class, 'health']);
        
        $this->app->post('/chat.php', [ChatController::class, 'chat']);
        $this->app->post('/chat', [ChatController::class, 'chat']);
        
        $this->app->options('/chat.php', [ChatController::class, 'options']);
        $this->app->options('/chat', [ChatController::class, 'options']);
        $this->app->options('/health.php', [ChatController::class, 'options']);
        $this->app->options('/health', [ChatController::class, 'options']);

        // Ruta raíz
        $this->app->get('/', function (ServerRequestInterface $request, ResponseInterface $response) {
            $config = $this->container->get(AppConfig::class);
            
            $data = [
                'service' => $config->get('app.name'),
                'version' => $config->get('app.version'),
                'status' => 'running',
                'environment' => 'test',
                'endpoints' => [
                    'POST /chat.php' => 'Chat with AI',
                    'GET /health.php' => 'Health check',
                    'GET /' => 'API information'
                ],
                'timestamp' => date('c')
            ];
            
            $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json');
        });

        // 404 handler
        $this->app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.*}', 
            function (ServerRequestInterface $request, ResponseInterface $response) {
                $data = [
                    'error' => 'Not Found',
                    'message' => 'The requested endpoint does not exist',
                    'requested_path' => $request->getUri()->getPath()
                ];
                
                $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
                return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus(404);
            }
        );
    }

    /**
     * Crea configuración específica para testing
     */
    protected function createTestConfig(): AppConfig
    {
        // Configuración base para testing
        $testConfig = [
            'app' => [
                'name' => 'Chatbot Demo API',
                'version' => '2.0.0',
                'environment' => 'test',
                'debug' => true
            ],
            'gemini' => [
                'api_key' => 'DEMO_MODE', // Por defecto en modo demo para tests
                'model' => 'gemini-pro',
                'temperature' => 0.7,
                'max_tokens' => 2048,
                'timeout' => 30
            ],
            'knowledge_base' => [
                'path' => __DIR__ . '/../../knowledge_test',
                'cache_enabled' => false, // Disable cache en tests
                'cache_ttl' => 3600
            ],
            'rate_limit' => [
                'enabled' => true,
                'max_requests' => 100, // Más permisivo en tests
                'time_window' => 900,
                'database_path' => '/tmp/test_rate_limit.db'
            ],
            'cors' => [
                'allowed_origins' => ['*'],
                'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
                'allowed_headers' => ['Content-Type', 'Authorization']
            ],
            'logging' => [
                'path' => '/tmp/test_app.log',
                'level' => 'debug'
            ]
        ];

        return AppConfig::createFromArray($testConfig);
    }

    /**
     * Factory methods para servicios - pueden ser sobrescritos en tests específicos
     */
    protected function createKnowledgeBaseService(AppConfig $config, LoggerInterface $logger): KnowledgeBaseService
    {
        return new KnowledgeBaseService($config, $logger);
    }

    protected function createRateLimitService(AppConfig $config, LoggerInterface $logger): RateLimitService
    {
        return new RateLimitService($config, $logger);
    }

    protected function createChatService(AppConfig $config, KnowledgeBaseService $knowledgeService, LoggerInterface $logger): ChatService
    {
        return new ChatService($config, $knowledgeService, $logger);
    }

    /**
     * Helper para crear requests HTTP simulados
     */
    protected function createRequest(
        string $method,
        string $uri,
        array $headers = [],
        string $body = ''
    ): ServerRequestInterface {
        $request = $this->requestFactory->createRequest($method, $uri);
        
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        
        if (!empty($body)) {
            $stream = $this->streamFactory->createStream($body);
            $request = $request->withBody($stream);
        }
        
        return $request;
    }

    /**
     * Helper para ejecutar request contra la aplicación
     */
    protected function runApp(ServerRequestInterface $request): ResponseInterface
    {
        return $this->app->handle($request);
    }

    /**
     * Helper para hacer requests JSON
     */
    protected function postJson(string $uri, array $data): ResponseInterface
    {
        $request = $this->createRequest(
            'POST',
            $uri,
            ['Content-Type' => 'application/json'],
            json_encode($data)
        );
        
        return $this->runApp($request);
    }

    /**
     * Helper para hacer requests GET
     */
    protected function get(string $uri): ResponseInterface
    {
        $request = $this->createRequest('GET', $uri);
        return $this->runApp($request);
    }

    /**
     * Helper para decodificar response JSON
     */
    protected function getJsonResponse(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();
        return json_decode($body, true) ?? [];
    }
}