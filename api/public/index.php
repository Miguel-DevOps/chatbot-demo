<?php

declare(strict_types=1);

use ChatbotDemo\Config\AppConfig;
use ChatbotDemo\Config\DependencyContainer;
use ChatbotDemo\Controllers\ChatController;
use ChatbotDemo\Controllers\HealthController;
use ChatbotDemo\Controllers\MetricsController;
use ChatbotDemo\Middleware\CorsMiddleware;
use ChatbotDemo\Middleware\ErrorHandlerMiddleware;
use ChatbotDemo\Middleware\MetricsMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

try {
    // Get the DI container
    $container = DependencyContainer::getInstance();
    
    // Configure Slim to use our DI container
    AppFactory::setContainer($container);
    
    // Get configuration and logger from container
    $config = $container->get(AppConfig::class);
    $logger = $container->get(LoggerInterface::class);
    
    // Configure error reporting
    if ($config->isDevelopment()) {
        error_reporting(E_ALL);
        ini_set('display_errors', '1');
    } else {
        error_reporting(0);
        ini_set('display_errors', '0');
    }

    // Configure timezone
    date_default_timezone_set($config->get('app.timezone', 'UTC'));

    // Log application startup
    $logger->info('Application starting', [
        'environment' => $config->get('app.environment'),
        'version' => $config->get('app.version'),
        'php_version' => PHP_VERSION
    ]);

    // Create Slim application
    $app = AppFactory::create();

    // Configure middleware stack (order matters - last added runs first)
    $app->addBodyParsingMiddleware();
    $app->addRoutingMiddleware();
    
    // Add our custom error handler middleware
    $app->add($container->get(ErrorHandlerMiddleware::class));
    
    // Add metrics middleware (before CORS so metrics capture all requests)
    $app->add($container->get(MetricsMiddleware::class));
    
    // Add CORS middleware
    $app->add($container->get(CorsMiddleware::class));

    // Routes - Controllers are automatically resolved from DI container
    // Legacy routes (backward compatibility)
    $app->get('/health.php', [HealthController::class, 'health']);
    $app->get('/health', [HealthController::class, 'health']);
    $app->post('/chat.php', [ChatController::class, 'chat']);
    $app->post('/chat', [ChatController::class, 'chat']);
    
    // API v1 routes
    $app->get('/api/v1/health', [HealthController::class, 'health']);
    $app->post('/api/v1/chat', [ChatController::class, 'chat']);
    $app->get('/api/v1/metrics', [MetricsController::class, 'metrics']);
    
    // Prometheus metrics endpoint (alternative path)
    $app->get('/metrics', [MetricsController::class, 'metrics']);
    
    // OPTIONS for CORS
    $app->options('/chat.php', [ChatController::class, 'options']);
    $app->options('/chat', [ChatController::class, 'options']);
    $app->options('/health.php', [ChatController::class, 'options']);
    $app->options('/health', [ChatController::class, 'options']);
    $app->options('/api/v1/chat', [ChatController::class, 'options']);
    $app->options('/api/v1/health', [ChatController::class, 'options']);
    $app->options('/api/v1/metrics', [ChatController::class, 'options']);
    $app->options('/metrics', [ChatController::class, 'options']);

    // Root route with API information
    $app->get('/', function (Request $request, Response $response) use ($config, $logger) {
        $data = [
            'service' => $config->get('app.name'),
            'version' => $config->get('app.version'),
            'status' => 'running',
            'environment' => $config->get('app.environment'),
            'endpoints' => [
                'POST /api/v1/chat' => 'Chat with AI',
                'POST /chat' => 'Chat with AI (legacy)',
                'GET /api/v1/health' => 'Health check',
                'GET /health' => 'Health check (legacy)',
                'GET /api/v1/metrics' => 'Prometheus metrics',
                'GET /metrics' => 'Prometheus metrics (alternative)',
                'GET /' => 'API information'
            ],
            'timestamp' => date('c')
        ];
        
        $logger->info('API info requested', [
            'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $request->getHeaderLine('User-Agent')
        ]);
        
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // Handle 404 for all other routes
    $app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.*}', function (Request $request, Response $response) use ($logger) {
        $requestedPath = $request->getUri()->getPath();
        
        $logger->warning('Route not found', [
            'path' => $requestedPath,
            'method' => $request->getMethod(),
            'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        $data = [
            'error' => 'Not Found',
            'message' => 'The requested endpoint does not exist',
            'requested_path' => $requestedPath,
            'available_endpoints' => [
                'GET /' => 'API information',
                'POST /api/v1/chat' => 'Chat endpoint',
                'POST /chat' => 'Chat endpoint (legacy)',
                'GET /api/v1/health' => 'Health check',
                'GET /health' => 'Health check (legacy)',
                'GET /api/v1/metrics' => 'Prometheus metrics',
                'GET /metrics' => 'Prometheus metrics (alternative)'
            ]
        ];
        
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(404);
    });

    // Run the application
    $app->run();

} catch (\Throwable $e) {
    // Fallback error handling if DI container fails
    http_response_code(500);
    header('Content-Type: application/json');
    
    $errorData = [
        'error' => 'Application Bootstrap Error',
        'message' => 'Failed to initialize application',
        'timestamp' => date('c')
    ];
    
    // Only show details in development
    $environment = $_ENV['APP_ENV'] ?? 'development';
    if ($environment === 'development' || isset($config) && $config->isDevelopment()) {
        $errorData['debug'] = [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ];
    }
    
    echo json_encode($errorData, JSON_PRETTY_PRINT);
    
    // Try to log the error if logger is available
    if (isset($logger)) {
        $logger->critical('Application bootstrap failed', [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}