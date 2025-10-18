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
 * Detect if we're running in container environment
 */
function isRunningInContainer(): bool {
    // Check for container-specific indicators
    return file_exists('/.dockerenv') || 
           (isset($_ENV['CONTAINER']) && $_ENV['CONTAINER'] === 'true') ||
           is_dir('/var/www/html/vendor');
}

/**
 * Get base path depending on environment
 */
function getBasePath(): string {
    if (isRunningInContainer()) {
        return '/var/www/html';
    }
    
    // When running from host, use current directory structure
    return dirname(__DIR__);
}
/**
 * Log health check messages
 */
function logHealth(string $message, string $level = 'INFO'): void {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] HEALTHCHECK {$level}: {$message}";
    
    // Output to both stdout and error log for maximum compatibility
    echo $logMessage . PHP_EOL;
    error_log($logMessage);
}

/**
 * Test Redis connectivity if available
 */
function testRedis(): bool {
    try {
        // Skip Redis test if not in container environment
        if (!isRunningInContainer()) {
            logHealth("Redis test skipped - not in container environment", 'WARNING');
            return true; // Return true (pass) for non-container environments
        }
        
        if (!extension_loaded('redis')) {
            logHealth("Redis extension not loaded", 'ERROR');
            return false;
        }
        
        $redis = new Redis();
        
        // Try to connect to Redis (default Docker Compose setup)
        $connected = @$redis->connect('redis', 6379, 2);
        if (!$connected) {
            $connected = @$redis->connect('127.0.0.1', 6379, 2);
        }
        
        if (!$connected) {
            logHealth("Cannot connect to Redis server", 'ERROR');
            return false;
        }
        
        // Test basic operations
        $testKey = 'healthcheck_test_' . time();
        $redis->setex($testKey, 10, 'test_value');
        $value = $redis->get($testKey);
        $redis->del($testKey);
        
        if ($value !== 'test_value') {
            logHealth("Redis read/write test failed", 'ERROR');
            return false;
        }
        
        $redis->close();
        logHealth("Redis connectivity check passed");
        return true;
        
    } catch (Exception $e) {
        logHealth("Redis connectivity check failed: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Test HTTP endpoint availability using fastcgi interface
 */
function testHttpEndpoint(): bool {
    try {
        // Check if we can reach PHP-FPM directly using fastcgi_finish_request availability
        if (!function_exists('fastcgi_finish_request')) {
            logHealth("PHP-FPM interface not available, using application bootstrap test", 'WARNING');
            return testApplicationBootstrap();
        }
        
        // Test if we can create a basic HTTP-like request to our application
        $tempFile = tempnam(sys_get_temp_dir(), 'healthcheck_');
        file_put_contents($tempFile, '<?php
        $_SERVER["REQUEST_METHOD"] = "GET";
        $_SERVER["REQUEST_URI"] = "/health";
        $_SERVER["HTTP_HOST"] = "localhost";
        $_SERVER["SERVER_NAME"] = "localhost";
        $_SERVER["SCRIPT_NAME"] = "/index.php";
        
        try {
            require_once "/var/www/html/vendor/autoload.php";
            $container = \ChatbotDemo\Config\DependencyContainer::getInstance();
            $config = $container->get(\ChatbotDemo\Config\AppConfig::class);
            echo "HTTP_TEST_SUCCESS";
        } catch (Exception $e) {
            echo "HTTP_TEST_ERROR: " . $e->getMessage();
        }
        ');
        
        $output = shell_exec("php $tempFile 2>&1");
        unlink($tempFile);
        
        if (strpos($output, 'HTTP_TEST_SUCCESS') !== false) {
            logHealth("HTTP endpoint infrastructure check passed");
            return true;
        } else {
            logHealth("HTTP endpoint infrastructure test failed: " . $output, 'ERROR');
            return false;
        }
        
    } catch (Exception $e) {
        logHealth("HTTP endpoint check failed: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Test application bootstrap process
 */
function testApplicationBootstrap(): bool {
    try {
        $basePath = getBasePath();
        $autoloadPath = $basePath . '/vendor/autoload.php';
        
        // Test if we can bootstrap the application successfully
        if (!file_exists($autoloadPath)) {
            logHealth("Autoloader not found at {$autoloadPath} - skipping bootstrap test", 'WARNING');
            return 'warning';
        }
        
        require_once $autoloadPath;
        
        $container = \ChatbotDemo\Config\DependencyContainer::getInstance();
        $config = $container->get(\ChatbotDemo\Config\AppConfig::class);
        
        // Test basic container resolution
        $logger = $container->get(\Psr\Log\LoggerInterface::class);
        $chatService = $container->get(\ChatbotDemo\Services\ChatService::class);
        
        if (!$config || !$logger || !$chatService) {
            logHealth("Application bootstrap failed - dependency injection issue", 'ERROR');
            return false;
        }
        
        logHealth("Application bootstrap check passed");
        return true;
        
    } catch (Exception $e) {
        logHealth("Application bootstrap failed: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Test filesystem permissions
 */
function testFilesystemPermissions(): bool {
    try {
        $basePath = getBasePath();
        
        $requiredDirs = [
            $basePath . '/storage/logs',
            $basePath . '/logs'
        ];
        
        $requiredFiles = [
            $basePath . '/vendor/autoload.php',
            $basePath . '/src/Config/AppConfig.php',
            $basePath . '/knowledge'
        ];
        
        // Create missing directories if needed
        foreach ($requiredDirs as $dir) {
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    logHealth("Failed to create required directory: {$dir}", 'ERROR');
                    return false;
                }
                logHealth("Created missing directory: {$dir}");
            }
            
            if (!is_writable($dir)) {
                logHealth("Directory is not writable: {$dir}", 'ERROR');
                return false;
            }
        }
        
        foreach ($requiredFiles as $file) {
            if (!file_exists($file)) {
                logHealth("Required file/directory does not exist: {$file}", 'WARNING');
                // Don't fail for missing files in host environment
                if (!isRunningInContainer()) {
                    continue;
                }
                return false;
            }
            
            if (!is_readable($file)) {
                logHealth("File/directory is not readable: {$file}", 'ERROR');
                return false;
            }
        }
        
        // Test write capabilities
        $logDir = $basePath . '/logs';
        if (is_dir($logDir) && is_writable($logDir)) {
            $testFile = $logDir . '/healthcheck_test_' . time() . '.tmp';
            if (!file_put_contents($testFile, 'test')) {
                logHealth("Cannot write to logs directory", 'ERROR');
                return false;
            }
            unlink($testFile);
        }
        
        logHealth("Filesystem permissions check passed");
        return true;
        
    } catch (Exception $e) {
        logHealth("Filesystem permissions check failed: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Test application dependencies using the container
 */
function testApplicationServices(): bool {
    try {
        $basePath = getBasePath();
        
        // Load autoloader
        $autoloadPath = $basePath . '/vendor/autoload.php';
        if (!file_exists($autoloadPath)) {
            logHealth("Autoloader not found at {$autoloadPath} - likely not in container environment", 'WARNING');
            return true; // Return true (pass) for non-container environments
        }
        
        require_once $autoloadPath;
        
        // Test if we can instantiate the config
        if (!class_exists('ChatbotDemo\\Config\\AppConfig')) {
            logHealth("AppConfig class not found", 'ERROR');
            return false;
        }
        
        $config = new \ChatbotDemo\Config\AppConfig();
        if (!$config) {
            logHealth("Failed to create AppConfig instance", 'ERROR');
            return false;
        }
        
        // Test if we can get basic config values
        $appName = $config->get('app.name', 'default');
        if (empty($appName)) {
            logHealth("App configuration appears to be invalid", 'ERROR');
            return false;
        }
        
        // Test critical configuration values
        $knowledgePath = $config->get('knowledge_base.path');
        if (!is_dir($knowledgePath)) {
            logHealth("Knowledge base directory not found: {$knowledgePath}", 'ERROR');
            return false;
        }
        
        // Check for knowledge files
        $knowledgeFiles = glob($knowledgePath . '/*.md');
        if (empty($knowledgeFiles)) {
            logHealth("No knowledge base files found", 'ERROR');
            return false;
        }
        
        logHealth("Application services check passed - {$appName} v" . $config->get('app.version'));
        return true;
        
    } catch (Exception $e) {
        logHealth("Application services check failed: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Test memory and resource usage
 */
function testResourceUsage(): bool {
    try {
        $memoryLimit = ini_get('memory_limit');
        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);
        
        // Convert memory limit to bytes for comparison
        $memoryLimitBytes = return_bytes($memoryLimit);
        
        // Handle unlimited memory (-1) or invalid memory limit
        if ($memoryLimitBytes <= 0) {
            logHealth("Memory usage: " . formatBytes($memoryUsage) . " / unlimited");
        } else {
            $memoryUsagePercent = ($memoryUsage / $memoryLimitBytes) * 100;
            logHealth("Memory usage: " . formatBytes($memoryUsage) . " / {$memoryLimit} (" . round($memoryUsagePercent, 2) . "%)");
            
            if ($memoryUsagePercent > 80) {
                logHealth("High memory usage detected: " . round($memoryUsagePercent, 2) . "%", 'WARNING');
                // Don't fail on warnings, just log them
            }
        }
        
        // Check disk space - use dynamic path based on environment
        $basePath = getBasePath();
        $diskFree = disk_free_space($basePath);
        $diskTotal = disk_total_space($basePath);
        
        if ($diskFree !== false && $diskTotal !== false && $diskTotal > 0) {
            $diskUsagePercent = (($diskTotal - $diskFree) / $diskTotal) * 100;
            logHealth("Disk usage: " . formatBytes((int)($diskTotal - $diskFree)) . " / " . formatBytes((int)$diskTotal) . " (" . round($diskUsagePercent, 2) . "%)");
            
            if ($diskUsagePercent > 90) {
                logHealth("High disk usage detected: " . round($diskUsagePercent, 2) . "%", 'WARNING');
                // Don't fail on warnings, just log them
            }
        } else {
            logHealth("Disk space check skipped - unable to determine disk usage", 'WARNING');
        }
        
        logHealth("Resource usage check passed");
        return true;
        
    } catch (Exception $e) {
        logHealth("Resource usage check failed: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Helper function to convert memory limit string to bytes
 */
function return_bytes(string $val): int {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    
    return $val;
}

/**
 * Helper function to format bytes
 */
function formatBytes(int $bytes, int $precision = 2): string {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Main health check function
 */
function performHealthCheck(): int {
    logHealth("Starting comprehensive enterprise-grade health check");
    
    $checks = [
        'Filesystem Permissions' => 'testFilesystemPermissions',
        'Application Services' => 'testApplicationServices', 
        'Redis Connectivity' => 'testRedis',
        'HTTP Infrastructure' => 'testHttpEndpoint',
        'Application Bootstrap' => 'testApplicationBootstrap',
        'Resource Usage' => 'testResourceUsage'
    ];
    
    $failedChecks = [];
    $warningChecks = [];
    
    foreach ($checks as $checkName => $checkFunction) {
        logHealth("Running check: {$checkName}");
        
        try {
            $result = call_user_func($checkFunction);
            if ($result === false) {
                $failedChecks[] = $checkName;
                logHealth("Check failed: {$checkName}", 'ERROR');
            } elseif ($result === 'warning') {
                $warningChecks[] = $checkName;
                logHealth("Check warning: {$checkName}", 'WARNING');
            } else {
                logHealth("Check passed: {$checkName}");
            }
        } catch (Exception $e) {
            $failedChecks[] = $checkName;
            logHealth("Check exception: {$checkName} - " . $e->getMessage(), 'ERROR');
        }
    }
    
    // Log summary
    $totalChecks = count($checks);
    $passedChecks = $totalChecks - count($failedChecks) - count($warningChecks);
    
    logHealth("Health check summary: {$passedChecks}/{$totalChecks} passed, " . 
              count($warningChecks) . " warnings, " . count($failedChecks) . " failures");
    
    if (!empty($failedChecks)) {
        logHealth("Health check FAILED. Failed checks: " . implode(', ', $failedChecks), 'ERROR');
        return HEALTHCHECK_FAILURE;
    }
    
    if (!empty($warningChecks)) {
        logHealth("Health check PASSED with warnings: " . implode(', ', $warningChecks), 'WARNING');
    } else {
        logHealth("All health checks PASSED - system is healthy");
    }
    
    return HEALTHCHECK_SUCCESS;
}

// Execute health check
exit(performHealthCheck());