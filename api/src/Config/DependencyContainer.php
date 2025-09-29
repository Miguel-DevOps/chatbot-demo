<?php

declare(strict_types=1);

namespace ChatbotDemo\Config;

use ChatbotDemo\Controllers\ChatController;
use ChatbotDemo\Controllers\HealthController;
use ChatbotDemo\Middleware\CorsMiddleware;
use ChatbotDemo\Middleware\ErrorHandlerMiddleware;
use ChatbotDemo\Services\ChatService;
use ChatbotDemo\Services\KnowledgeBaseService;
use ChatbotDemo\Services\RateLimitService;
use DI\Container;
use DI\ContainerBuilder;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
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
                
                $logger->pushHandler(new StreamHandler($logPath, $logLevel));
                
                // Add console handler in development
                if ($config->isDevelopment()) {
                    $logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
                }
                
                return $logger;
            },

            // Services with automatic dependency injection
            KnowledgeBaseService::class => function (AppConfig $config, LoggerInterface $logger) {
                return new KnowledgeBaseService($config, $logger);
            },

            RateLimitService::class => function (AppConfig $config, LoggerInterface $logger) {
                return new RateLimitService($config, $logger);
            },

            ChatService::class => function (
                AppConfig $config, 
                KnowledgeBaseService $knowledgeService, 
                LoggerInterface $logger
            ) {
                return new ChatService($config, $knowledgeService, $logger);
            },

            // Controllers with automatic dependency injection
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
     * Reset the container instance (useful for testing)
     */
    public static function reset(): void
    {
        self::$container = null;
    }
}