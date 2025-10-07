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
use ChatbotDemo\Services\GenerativeAiClientInterface;
use ChatbotDemo\Services\KnowledgeBaseService;
use ChatbotDemo\Services\RateLimitService;
use ChatbotDemo\Services\TracingService;
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
    protected array $testConfigOverrides = [];

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
        // Limpiar container después de cada test
        DependencyContainer::reset();
        
        parent::tearDown();
        
        // Reset config overrides al final
        $this->testConfigOverrides = [];
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

        // Configure routes (same as production)
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

            // TracingService para testing (mock simplificado)
            TracingService::class => function (LoggerInterface $logger) {
                return new TracingService($logger, 'chatbot-test');
            },

            // AI Client mock para testing
            GenerativeAiClientInterface::class => function () {
                return new class implements GenerativeAiClientInterface {
                    public function generateContent(string $prompt): string
                    {
                        return "Mock AI response for: " . substr($prompt, 0, 50) . "...";
                    }

                    public function getProviderName(): string
                    {
                        return 'test-mock';
                    }

                    public function isAvailable(): bool
                    {
                        return true;
                    }
                };
            },

            // Servicios - pueden ser sobrescritos en tests específicos
            KnowledgeBaseService::class => function (AppConfig $config, LoggerInterface $logger) {
                return $this->createKnowledgeBaseService($config, $logger);
            },

            RateLimitService::class => function (AppConfig $config, LoggerInterface $logger) {
                return $this->createRateLimitService($config, $logger);
            },

            ChatService::class => function (
                GenerativeAiClientInterface $aiClient,
                KnowledgeBaseService $knowledgeService,
                LoggerInterface $logger,
                TracingService $tracingService
            ) {
                return $this->createChatService($aiClient, $knowledgeService, $logger, $tracingService);
            },

            // Controllers con autowiring
            ChatController::class => function (
                ChatService $chatService,
                RateLimitService $rateLimitService,
                AppConfig $config,
                LoggerInterface $logger,
                TracingService $tracingService
            ) {
                return new ChatController($chatService, $rateLimitService, $config, $logger, $tracingService);
            },

            HealthController::class => function (AppConfig $config, LoggerInterface $logger, RateLimitService $rateLimitService) {
                return new HealthController($config, $logger, $rateLimitService);
            },

            // Middleware
            CorsMiddleware::class => function (AppConfig $config, LoggerInterface $logger) {
                return new CorsMiddleware($config, $logger);
            },

            ErrorHandlerMiddleware::class => function (LoggerInterface $logger, AppConfig $config, TracingService $tracingService) {
                return new ErrorHandlerMiddleware($logger, $config, $tracingService);
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
                'path' => __DIR__ . '/knowledge_test',
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

        // Permitir overrides de configuración para tests específicos
        if (isset($this->testConfigOverrides)) {
            $testConfig = $this->mergeConfigArrays($testConfig, $this->testConfigOverrides);
        }

        return AppConfig::createFromArray($testConfig);
    }

    /**
     * Merge profundo de arrays de configuración que sobrescribe valores escalares
     */
    private function mergeConfigArrays(array $base, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = $this->mergeConfigArrays($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }
        return $base;
    }

    /**
     * Permite establecer configuración personalizada para un test específico
     */
    protected function setTestConfigOverrides(array $overrides): void
    {
        $this->testConfigOverrides = $overrides;
    }

    /**
     * Reconfigura la aplicación con nuevos overrides (útil para tests que necesitan cambiar configuración)
     */
    protected function reconfigureApp(array $configOverrides): void
    {
        $this->testConfigOverrides = $configOverrides;
        
        // Reset container con nueva configuración
        DependencyContainer::reset();
        
        // Reconfigurar aplicación
        $this->setupApplication();
    }

    /**
     * Factory methods para servicios - pueden ser sobrescritos en tests específicos
     */
    protected function createKnowledgeBaseService(AppConfig $config, LoggerInterface $logger): KnowledgeBaseService
    {
        // Use FilesystemKnowledgeProvider for testing
        $knowledgeProvider = new \ChatbotDemo\Repositories\FilesystemKnowledgeProvider($config, $logger);
        return new KnowledgeBaseService($config, $logger, $knowledgeProvider);
    }

    protected function createRateLimitService(AppConfig $config, LoggerInterface $logger): RateLimitService
    {
        // Use SqliteRateLimitStorage for testing
        $storage = new \ChatbotDemo\Repositories\SqliteRateLimitStorage($config, $logger);
        return new RateLimitService($config, $logger, $storage);
    }

    protected function createChatService(GenerativeAiClientInterface $aiClient, KnowledgeBaseService $knowledgeService, LoggerInterface $logger, TracingService $tracingService): ChatService
    {
        return new ChatService($aiClient, $knowledgeService, $logger, $tracingService);
    }

    /**
     * Helper para crear requests HTTP simulados
     */
    protected function createRequest(
        string $method,
        string $uri,
        array $headers = [],
        string $body = '',
        array $serverParams = []
    ): ServerRequestInterface {
        $request = $this->requestFactory->createRequest($method, $uri);
        
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        
        if (!empty($body)) {
            $stream = $this->streamFactory->createStream($body);
            $request = $request->withBody($stream);
        }
        
        // Agregar parámetros del servidor por defecto para testing
        $defaultServerParams = [
            'REMOTE_ADDR' => '127.0.0.1',
            'SERVER_NAME' => 'localhost',
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $uri
        ];
        $finalServerParams = array_merge($defaultServerParams, $serverParams);
        
        // Usar reflection para establecer serverParams ya que no hay método público
        $reflection = new \ReflectionClass($request);
        $property = $reflection->getProperty('serverParams');
        $property->setAccessible(true);
        $property->setValue($request, $finalServerParams);
        
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