<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "=== Debug Script ===\n";

try {
    echo "Loading autoloader...\n";
    require_once __DIR__ . '/vendor/autoload.php';
    echo "Autoloader loaded successfully\n";

    echo "Creating DependencyContainer...\n";
    $container = \ChatbotDemo\Config\DependencyContainer::create();
    echo "Container created successfully\n";

    echo "Getting MetricsMiddleware...\n";
    $middleware = $container->get(\ChatbotDemo\Middleware\MetricsMiddleware::class);
    echo "MetricsMiddleware retrieved successfully\n";

} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}