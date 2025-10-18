<?php

declare(strict_types=1);

namespace ChatbotDemo\Tests\Integration;

use ChatbotDemo\Config\AppConfig;
use ChatbotDemo\Config\DependencyContainer;
use ChatbotDemo\Controllers\ChatController;
use ChatbotDemo\Controllers\HealthController;
use ChatbotDemo\Middleware\CorsMiddleware;
use ChatbotDemo\Middleware\ErrorHandlerMiddleware;

use ChatbotDemo\Middleware\ValidationMiddleware;
use ChatbotDemo\Services\ChatService;
use ChatbotDemo\Services\GenerativeAiClientInterface;
use ChatbotDemo\Services\KnowledgeBaseService;
use ChatbotDemo\Services\RateLimitService;
use DI\Container;
use DI\ContainerBuilder;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Globals;
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
 * Base class for integration tests
 * 
 * Configures a complete Slim application in memory for testing,
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
        
        // Reset container for each test
        DependencyContainer::reset();
        
        // Configure factories to create requests/responses
        $this->requestFactory = new RequestFactory();
        $this->streamFactory = new StreamFactory();
        $this->uriFactory = new UriFactory();
        
        // Create Slim application in memory
        $this->setupApplication();
    }

    protected function tearDown(): void
    {
        // Clean up container after each test
        DependencyContainer::reset();
        
        parent::tearDown();
        
        // Reset config overrides al final
        $this->testConfigOverrides = [];
    }

    /**
     * Configures the Slim application with custom DI container for testing
     */
    protected function setupApplication(): void
    {
        // Create container with testing configuration
        $this->container = $this->buildTestContainer();
        
        // Configure Slim with the container
        AppFactory::setContainer($this->container);
        $this->app = AppFactory::create();

        // Configure middleware stack (same order as production)
        $this->app->addBodyParsingMiddleware();
        $this->app->add(new ValidationMiddleware()); // Add validation middleware after body parsing
        // Note: Rate limiting is handled in ChatController, not as middleware
        $this->app->addRoutingMiddleware();
        
        // Middleware personalizado de manejo de errores
        $this->app->add($this->container->get(ErrorHandlerMiddleware::class));
        
        // Middleware CORS
        $this->app->add($this->container->get(CorsMiddleware::class));

        // Configure routes (same as production)
        $this->setupRoutes();
    }

    /**
     * Builds a specific DI container for testing
     */
    private function buildTestContainer(): Container
    {
        $builder = new ContainerBuilder();
        
        $builder->addDefinitions([
            // Testing configuration
            AppConfig::class => function () {
                return $this->createTestConfig();
            },

            // Silent logger for testing
            LoggerInterface::class => function () {
                $logger = new Logger('test');
                $logger->pushHandler(new NullHandler()); // No output durante tests
                return $logger;
            },

            // TracerInterface for testing using global OpenTelemetry
            TracerInterface::class => function () {
                return Globals::tracerProvider()->getTracer('chatbot-test', '1.0.0');
            },

            // AI Client mock for testing
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

            // Services - can be overridden in specific tests
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
                TracerInterface $tracer
            ) {
                return $this->createChatService($aiClient, $knowledgeService, $logger, $tracer);
            },

            // Controllers con autowiring
            ChatController::class => function (
                ChatService $chatService,
                RateLimitService $rateLimitService,
                AppConfig $config,
                LoggerInterface $logger,
                TracerInterface $tracer
            ) {
                return new ChatController($chatService, $rateLimitService, $config, $logger, $tracer);
            },

            HealthController::class => function (AppConfig $config, LoggerInterface $logger, RateLimitService $rateLimitService) {
                return new HealthController($config, $logger, $rateLimitService);
            },

            // Middleware
            CorsMiddleware::class => function (AppConfig $config, LoggerInterface $logger) {
                return new CorsMiddleware($config, $logger);
            },



            ErrorHandlerMiddleware::class => function (LoggerInterface $logger, AppConfig $config, TracerInterface $tracer) {
                return new ErrorHandlerMiddleware($logger, $config, $tracer);
            }
        ]);

        return $builder->build();
    }

    /**
     * Configures the application routes
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

        // Root route
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
     * Creates specific configuration for testing
     */
    protected function createTestConfig(): AppConfig
    {
        // Base configuration for testing
        $testConfig = [
            'app' => [
                'name' => 'Chatbot Demo API',
                'version' => '2.0.0',
                'environment' => 'test',
                'debug' => true
            ],
            'gemini' => [
                'api_key' => 'DEMO_MODE', // Default to demo mode for tests
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
                'max_requests' => 100, // More permissive in tests
                'time_window' => 900,
                'database_path' => '/tmp/test_rate_limit.db'
            ],
            'redis' => [
                'host' => 'localhost',
                'port' => 6379,
                'password' => null,
                'database' => 0, // Default Redis database for tests
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

        // Allow configuration overrides for specific tests
        if (isset($this->testConfigOverrides)) {
            $testConfig = $this->mergeConfigArrays($testConfig, $this->testConfigOverrides);
        }

        return AppConfig::createFromArray($testConfig);
    }

    /**
     * Deep merge of configuration arrays that overwrites scalar values
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
     * Allows setting custom configuration for a specific test
     */
    protected function setTestConfigOverrides(array $overrides): void
    {
        $this->testConfigOverrides = $overrides;
    }

    /**
     * Reconfigures the application with new overrides (useful for tests that need to change configuration)
     */
    protected function reconfigureApp(array $configOverrides): void
    {
        $this->testConfigOverrides = $configOverrides;
        
        // Reset container with new configuration
        DependencyContainer::reset();
        
        // Reconfigure application
        $this->setupApplication();
    }

    /**
     * Factory methods for services - can be overridden in specific tests
     */
    protected function createKnowledgeBaseService(AppConfig $config, LoggerInterface $logger): KnowledgeBaseService
    {
        // Use FilesystemKnowledgeProvider for testing
        $knowledgeProvider = new \ChatbotDemo\Repositories\FilesystemKnowledgeProvider($config, $logger);
        return new KnowledgeBaseService($config, $logger, $knowledgeProvider);
    }

    protected function createRateLimitService(AppConfig $config, LoggerInterface $logger): RateLimitService
    {
        // Check if test wants to use in-memory storage
        if (isset($this->testConfigOverrides['use_memory_rate_limit']) && $this->testConfigOverrides['use_memory_rate_limit']) {
            $storage = new \ChatbotDemo\Tests\Fixtures\InMemoryRateLimitStorage();
        } else {
            // Use RedisRateLimitStorage for testing to maintain consistency with production
            $storage = new \ChatbotDemo\Repositories\RedisRateLimitStorage($config, $logger);
        }
        return new RateLimitService($config, $logger, $storage);
    }

    protected function createChatService(GenerativeAiClientInterface $aiClient, KnowledgeBaseService $knowledgeService, LoggerInterface $logger, TracerInterface $tracer): ChatService
    {
        return new ChatService($aiClient, $knowledgeService, $logger, $tracer);
    }

    /**
     * Helper to create simulated HTTP requests
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
        
        // Add default server parameters for testing
        $defaultServerParams = [
            'REMOTE_ADDR' => '127.0.0.1',
            'SERVER_NAME' => 'localhost',
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $uri
        ];
        $finalServerParams = array_merge($defaultServerParams, $serverParams);
        
        // Use reflection to set serverParams since there's no public method
        $reflection = new \ReflectionClass($request);
        $property = $reflection->getProperty('serverParams');
        $property->setAccessible(true);
        $property->setValue($request, $finalServerParams);
        
        return $request;
    }

    /**
     * Helper to execute request against the application
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