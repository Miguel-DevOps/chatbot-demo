<?php
/**
 * Health Check Script for Chatbot Demo API
 * 
 * This script performs comprehensive health checks for the application:
 * - Tests HTTP endpoint availability
 * - Validates Redis connectivity
 * - Checks essential services through dependency container
 * - Returns appropriate exit codes for Docker healthcheck
 */

declare(strict_types=1);

// Set error reporting for health check
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Define constants
define('HEALTHCHECK_TIMEOUT', 5);
define('HEALTHCHECK_SUCCESS', 0);
define('HEALTHCHECK_FAILURE', 1);

/**
 * Log health check messages
 */
function logHealth(string $message, string $level = 'INFO'): void {
    $timestamp = date('Y-m-d H:i:s');
    error_log("[{$timestamp}] HEALTHCHECK {$level}: {$message}");
}

/**
 * Test Redis connectivity
 */
function testRedis(): bool {
    try {
        $redis = new Redis();
        
        // Get configuration
        $host = $_ENV['REDIS_HOST'] ?? 'redis';
        $port = (int)($_ENV['REDIS_PORT'] ?? 6379);
        $timeout = 2.0;
        
        // Attempt connection
        if (!$redis->connect($host, $port, $timeout)) {
            logHealth("Failed to connect to Redis at {$host}:{$port}", 'ERROR');
            return false;
        }
        
        // Test ping
        $response = $redis->ping();
        if ($response !== '+PONG') {
            logHealth("Redis ping failed. Response: " . var_export($response, true), 'ERROR');
            $redis->close();
            return false;
        }
        
        $redis->close();
        logHealth("Redis connectivity check passed");
        return true;
        
    } catch (Exception $e) {
        logHealth("Redis check failed: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Test HTTP endpoint availability using internal PHP
 */
function testHttpEndpoint(): bool {
    try {
        // Test internal health endpoint
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => HEALTHCHECK_TIMEOUT,
                'header' => [
                    'User-Agent: HealthCheck/1.0',
                    'Accept: application/json'
                ]
            ]
        ]);
        
        // Try to hit the health endpoint
        $baseUrl = $_ENV['APP_URL'] ?? 'http://localhost:8080';
        $healthUrl = rtrim($baseUrl, '/') . '/health';
        
        $response = @file_get_contents($healthUrl, false, $context);
        
        if ($response === false) {
            logHealth("HTTP health endpoint not reachable: {$healthUrl}", 'ERROR');
            return false;
        }
        
        // Try to decode JSON response
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            logHealth("Invalid JSON response from health endpoint", 'ERROR');
            return false;
        }
        
        // Check if status is OK
        if (!isset($data['status']) || $data['status'] !== 'ok') {
            logHealth("Health endpoint returned non-OK status", 'ERROR');
            return false;
        }
        
        logHealth("HTTP endpoint check passed");
        return true;
        
    } catch (Exception $e) {
        logHealth("HTTP endpoint check failed: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Test application dependencies using the container
 */
function testApplicationServices(): bool {
    try {
        // Load autoloader
        $autoloadPath = '/var/www/html/vendor/autoload.php';
        if (!file_exists($autoloadPath)) {
            logHealth("Autoloader not found at {$autoloadPath}", 'ERROR');
            return false;
        }
        
        require_once $autoloadPath;
        
        // Test if we can instantiate the config
        if (!class_exists('ChatbotDemo\\Config\\AppConfig')) {
            logHealth("AppConfig class not found", 'ERROR');
            return false;
        }
        
        $config = \ChatbotDemo\Config\AppConfig::getInstance();
        if (!$config) {
            logHealth("Failed to get AppConfig instance", 'ERROR');
            return false;
        }
        
        // Test if we can get basic config values
        $appName = $config->get('app.name', 'default');
        if (empty($appName)) {
            logHealth("App configuration appears to be invalid", 'ERROR');
            return false;
        }
        
        logHealth("Application services check passed");
        return true;
        
    } catch (Exception $e) {
        logHealth("Application services check failed: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Test filesystem permissions
 */
function testFilesystemPermissions(): bool {
    try {
        $requiredDirs = [
            '/var/www/html/storage/logs',
            '/var/www/html/logs'
        ];
        
        foreach ($requiredDirs as $dir) {
            if (!is_dir($dir)) {
                logHealth("Required directory does not exist: {$dir}", 'ERROR');
                return false;
            }
            
            if (!is_writable($dir)) {
                logHealth("Directory is not writable: {$dir}", 'ERROR');
                return false;
            }
        }
        
        logHealth("Filesystem permissions check passed");
        return true;
        
    } catch (Exception $e) {
        logHealth("Filesystem permissions check failed: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Main health check function
 */
function performHealthCheck(): int {
    logHealth("Starting comprehensive health check");
    
    $checks = [
        'Filesystem Permissions' => 'testFilesystemPermissions',
        'Application Services' => 'testApplicationServices',
        'Redis Connectivity' => 'testRedis',
        'HTTP Endpoint' => 'testHttpEndpoint'
    ];
    
    $failedChecks = [];
    
    foreach ($checks as $checkName => $checkFunction) {
        logHealth("Running check: {$checkName}");
        
        if (!call_user_func($checkFunction)) {
            $failedChecks[] = $checkName;
            logHealth("Check failed: {$checkName}", 'ERROR');
        }
    }
    
    if (!empty($failedChecks)) {
        logHealth("Health check FAILED. Failed checks: " . implode(', ', $failedChecks), 'ERROR');
        return HEALTHCHECK_FAILURE;
    }
    
    logHealth("All health checks PASSED");
    return HEALTHCHECK_SUCCESS;
}

// Execute health check
exit(performHealthCheck());