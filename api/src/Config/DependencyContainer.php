<?php

declare(strict_types=1);

namespace ChatbotDemo\Config;

use ChatbotDemo\Controllers\ChatController;
use ChatbotDemo\Controllers\HealthController;
use ChatbotDemo\Controllers\MetricsController;
use ChatbotDemo\Config\OpenTelemetryBootstrap;
use ChatbotDemo\Middleware\CorsMiddleware;
use ChatbotDemo\Middleware\ErrorHandlerMiddleware;
use ChatbotDemo\Middleware\MetricsMiddleware;
use ChatbotDemo\Repositories\RateLimitStorageInterface;
use ChatbotDemo\Repositories\RedisRateLimitStorage;
use ChatbotDemo\Repositories\KnowledgeProviderInterface;
use ChatbotDemo\Repositories\FilesystemKnowledgeProvider;
use ChatbotDemo\Services\ChatService;
use ChatbotDemo\Services\GenerativeAiClientInterface;
use ChatbotDemo\Services\GeminiApiClient;
use ChatbotDemo\Services\KnowledgeBaseService;
use ChatbotDemo\Services\RateLimitService;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Globals;
use DI\Container;
use DI\ContainerBuilder;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\JsonFormatter;
use Monolog\Logger;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\APC;
use Prometheus\Storage\InMemory;
use Prometheus\Storage\Redis;
use Psr\Log\LoggerInterface;

class DependencyContainer
{
    private static ?Container $container = null;

    public static function getInstance(): Container
    {
        if (self::$container === null) {
            self::$container = self::buildContainer();
        }

        return self::$container;
    }

    private static function buildContainer(): Container
    {
        // Initialize OpenTelemetry SDK early
        OpenTelemetryBootstrap::initialize();
        
        $builder = new ContainerBuilder();
        
        // Enable compilation in production for better performance
        $config = AppConfig::getInstance();
        if (!$config->isDevelopment()) {
            $builder->enableCompilation(__DIR__ . '/../../cache');
        }

        $builder->addDefinitions([
            // Configuration singleton
            AppConfig::class => function () {
                return AppConfig::getInstance();
            },

            // Logger configuration
            LoggerInterface::class => function (AppConfig $config) {
                $logger = new Logger($config->get('app.name', 'chatbot-api'));
                
                $logLevel = $config->isDevelopment() ? Logger::DEBUG : Logger::INFO;
                $logPath = $config->get('logging.path', __DIR__ . '/../../logs/app.log');
                
                // Ensure log directory exists
                $logDir = dirname($logPath);
                if (!is_dir($logDir)) {
                    mkdir($logDir, 0755, true);
                }
                
                // Configure JSON formatter for structured logging
                $jsonFormatter = new JsonFormatter(JsonFormatter::BATCH_MODE_NEWLINES, true);
                
                // File handler with JSON formatter
                $fileHandler = new StreamHandler($logPath, $logLevel);
                $fileHandler->setFormatter($jsonFormatter);
                $logger->pushHandler($fileHandler);
                
                // Stdout handler with JSON formatter for Docker
                $stdoutHandler = new StreamHandler('php://stdout', $logLevel);
                $stdoutHandler->setFormatter($jsonFormatter);
                $logger->pushHandler($stdoutHandler);
                
                return $logger;
            },

            // OpenTelemetry Tracer
            TracerInterface::class => function () {
                return Globals::tracerProvider()->getTracer('chatbot-api');
            },

            // AI Client abstraction
            GenerativeAiClientInterface::class => function (AppConfig $config, LoggerInterface $logger) {
                return new GeminiApiClient($config, $logger);
            },

            // Services with automatic dependency injection
            KnowledgeProviderInterface::class => function (AppConfig $config, LoggerInterface $logger) {
                return new FilesystemKnowledgeProvider($config, $logger);
            },

            KnowledgeBaseService::class => function (
                AppConfig $config, 
                LoggerInterface $logger,
                KnowledgeProviderInterface $knowledgeProvider
            ) {
                return new KnowledgeBaseService($config, $logger, $knowledgeProvider);
            },

            // Rate Limiting Storage abstraction
            RateLimitStorageInterface::class => function (AppConfig $config, LoggerInterface $logger) {
                // Only Redis implementation - no fallback to SQLite for cloud-native deployment
                // Application will fail fast if Redis is not available, forcing proper infrastructure
                return new RedisRateLimitStorage($config, $logger);
            },

            RateLimitService::class => function (
                AppConfig $config, 
                LoggerInterface $logger,
                RateLimitStorageInterface $storage
            ) {
                return new RateLimitService($config, $logger, $storage);
            },

            ChatService::class => function (
                GenerativeAiClientInterface $aiClient,
                KnowledgeBaseService $knowledgeService, 
                LoggerInterface $logger,
                TracerInterface $tracer
            ) {
                return new ChatService($aiClient, $knowledgeService, $logger, $tracer);
            },

            // Controllers with automatic dependency injection
            ChatController::class => function (
                ChatService $chatService, 
                RateLimitService $rateLimitService,
                AppConfig $config, 
                LoggerInterface $logger,
                TracerInterface $tracer
            ) {
                return new ChatController($chatService, $rateLimitService, $config, $logger, $tracer);
            },

            HealthController::class => function (
                AppConfig $config, 
                LoggerInterface $logger,
                RateLimitService $rateLimitService
            ) {
                return new HealthController($config, $logger, $rateLimitService);
            },

            MetricsController::class => function (
                MetricsMiddleware $metricsMiddleware,
                LoggerInterface $logger
            ) {
                return new MetricsController($metricsMiddleware, $logger);
            },

            // Prometheus registry with Redis storage
            CollectorRegistry::class => function (AppConfig $config, LoggerInterface $logger) {
                // Redis configuration from environment variables
                $redisHost = $_ENV['REDIS_HOST'] ?? $config->get('redis.host', 'localhost');
                $redisPort = (int) ($_ENV['REDIS_PORT'] ?? $config->get('redis.port', 6379));
                $redisPassword = $_ENV['REDIS_PASSWORD'] ?? $config->get('redis.password', null);
                $redisDatabase = (int) ($_ENV['REDIS_DATABASE'] ?? $config->get('redis.database', 0));
                
                try {
                    // Configure Redis connection
                    $redisOptions = [
                        'host' => $redisHost,
                        'port' => $redisPort,
                        'database' => $redisDatabase,
                        'timeout' => 0.1,
                    ];
                    
                    if (!empty($redisPassword)) {
                        $redisOptions['password'] = $redisPassword;
                    }
                    
                    $logger->info('Connecting to Redis for metrics storage', [
                        'host' => $redisHost,
                        'port' => $redisPort,
                        'database' => $redisDatabase
                    ]);
                    
                    return new CollectorRegistry(new Redis($redisOptions));
                } catch (\Exception $e) {
                    $logger->warning('Redis not available for metrics, falling back to APCu', [
                        'error' => $e->getMessage(),
                        'redis_host' => $redisHost,
                        'redis_port' => $redisPort
                    ]);
                    
                    // Fallback to APCu if Redis is not available
                    try {
                        if (extension_loaded('apcu') && apcu_enabled()) {
                            return new CollectorRegistry(new APC());
                        }
                        
                        // If APCu is not enabled, fallback to in-memory
                        $logger->warning('APCu not enabled, falling back to in-memory storage');
                        return new CollectorRegistry(new InMemory());
                    } catch (\Exception $apcException) {
                        $logger->warning('APCu not available, falling back to in-memory storage', [
                            'apc_error' => $apcException->getMessage()
                        ]);
                        
                        // Final fallback to in-memory storage
                        return new CollectorRegistry(new InMemory());
                    }
                }
            },

                        // Middleware
            MetricsMiddleware::class => function (LoggerInterface $logger, CollectorRegistry $registry) {
                return new MetricsMiddleware($logger, $registry);
            },

            CorsMiddleware::class => function (AppConfig $config, LoggerInterface $logger) {
                return new CorsMiddleware($config, $logger);
            },

            ErrorHandlerMiddleware::class => function (LoggerInterface $logger, AppConfig $config, TracerInterface $tracer) {
                return new ErrorHandlerMiddleware($logger, $config, $tracer);
            },
        ]);

        return $builder->build();
    }

    /**
     * Reset the container instance (useful for testing)
     */
    public static function reset(): void
    {
        self::$container = null;
    }
}