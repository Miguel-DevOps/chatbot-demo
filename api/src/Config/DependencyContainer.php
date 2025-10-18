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
        // Create temporary AppConfig instance to check environment
        $tempConfig = new AppConfig();
        if (!$tempConfig->isDevelopment()) {
            $cacheDir = __DIR__ . '/../../cache';
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0755, true);
            }
            $builder->enableCompilation($cacheDir);
        }
        
        $builder->addDefinitions([
            // Configuration - single instance managed by DI container
            AppConfig::class => function () {
                return new AppConfig();
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
                $redisHost = $config->get('redis.host', 'localhost');
                $redisPort = $config->get('redis.port', 6379);

                try {
                    $redisOptions = ['host' => $redisHost, 'port' => $redisPort];

                    // CRITICAL FIX! Use the correct adapter from promphp/prometheus_client_php
                    // Must use \Prometheus\Storage\Redis, not instantiate a \Redis object.
                    $adapter = new \Prometheus\Storage\Redis($redisOptions);

                    $logger->info('Successfully configured Prometheus Redis adapter.', $redisOptions);

                    return new CollectorRegistry($adapter);

                } catch (\Exception $e) {
                    $logger->error('Failed to connect to Redis for metrics. Application will not start.', [
                        'error' => $e->getMessage(),
                        'redis_host' => $redisHost,
                        'redis_port' => $redisPort
                    ]);

                    // Fail-Fast Principle: If Redis is down, the application should not start.
                    throw new \RuntimeException('Metrics storage (Redis) is unavailable.', 0, $e);
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