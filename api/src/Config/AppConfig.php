<?php

declare(strict_types=1);

namespace ChatbotDemo\Config;

use Dotenv\Dotenv;
use RuntimeException;

/**
 * Centralized application configuration
 * Following the 12-Factor App configuration pattern
 */
class AppConfig
{
    private array $config = [];
    private static ?AppConfig $instance = null;

    private function __construct()
    {
        $this->loadEnvironmentVariables();
        $this->initializeConfig();
    }

    public static function getInstance(): AppConfig
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function loadEnvironmentVariables(): void
    {
        $envPath = dirname(__DIR__, 2);
        
        if (file_exists($envPath . '/.env')) {
            $dotenv = Dotenv::createImmutable($envPath);
            $dotenv->load();
        }
    }

    private function initializeConfig(): void
    {
        $this->config = [
            'app' => [
                'name' => 'Chatbot Demo API',
                'version' => '2.0.0',
                'environment' => $this->getEnv('NODE_ENV', 'development'),
                'debug' => $this->getEnv('DEBUG_MODE', 'false') === 'true',
                'timezone' => 'UTC'
            ],
            'ai' => [
                'provider' => $_ENV['AI_PROVIDER'] ?? 'demo',
                'api_key' => $_ENV['GEMINI_API_KEY'] ?? null,
                'model' => $_ENV['AI_MODEL'] ?? 'gemini-1.5-flash',
            ],
            
            'redis' => [
                'host' => $_ENV['REDIS_HOST'] ?? 'localhost',
                'port' => (int)($_ENV['REDIS_PORT'] ?? 6379),
                'password' => $_ENV['REDIS_PASSWORD'] ?? null,
                'database' => (int)($_ENV['REDIS_DATABASE'] ?? 0),
            ],
            'rate_limit' => [
                'max_requests' => (int) $this->getEnv('RATE_LIMIT_MAX_REQUESTS', '50'),
                'time_window' => (int) $this->getEnv('RATE_LIMIT_TIME_WINDOW', '900'),
                'database_path' => dirname(__DIR__, 1) . '/data/rate_limit.db'
            ],
            'knowledge_base' => [
                'path' => dirname(__DIR__, 2) . '/knowledge',
                'cache_enabled' => true,
                'cache_ttl' => 3600 // 1 hora
            ],
            'cors' => [
                'allowed_origins' => explode(',', $this->getEnv('CORS_ORIGINS', '*')),
                'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
                'allowed_headers' => ['Content-Type', 'Authorization']
            ],
            'logging' => [
                'level' => $this->getEnv('LOG_LEVEL', 'INFO'),
                'path' => dirname(__DIR__, 1) . '/logs/app.log'
            ]
        ];
    }

    private function getEnv(string $key, string $default = ''): string
    {
        return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }

    public function get(string $path, $default = null)
    {
        $keys = explode('.', $path);
        $value = $this->config;

        foreach ($keys as $key) {
            if (!isset($value[$key])) {
                return $default;
            }
            $value = $value[$key];
        }

        return $value;
    }

    public function getGeminiApiKey(): string
    {
        $apiKey = $this->get('gemini.api_key');
        
        if (empty($apiKey) || $apiKey === 'gemini_api_key_here') {
            if ($this->isProduction()) {
                throw new RuntimeException('GEMINI_API_KEY no está configurada para producción');
            }
            return 'DEMO_MODE';
        }
        
        return $apiKey;
    }

    public function isProduction(): bool
    {
        return $this->get('app.environment') === 'production';
    }

    public function isDevelopment(): bool
    {
        return $this->get('app.environment') === 'development';
    }

    public function isDebugEnabled(): bool
    {
        return $this->get('app.debug', false);
    }

    /**
     * Create configuration instance from array (for testing)
     */
    public static function createFromArray(array $config): AppConfig
    {
        $instance = new self();
        $instance->config = $config;
        return $instance;
    }

    /**
     * Obtain entire configuration as array (for testing)
     */
    public function toArray(): array
    {
        return $this->config;
    }

    /**
     * Reset singleton instance (for testing)
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}