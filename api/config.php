<?php
/**
 * Configuración centralizada de variables de entorno
 * Archivo: api/config.php
 * 
 * SEGURIDAD: Este archivo carga las variables de entorno de forma segura
 * y proporciona valores por defecto para desarrollo/testing
 */

// Cargar autoloader de Composer
$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
    
    // Cargar variables de entorno
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    try {
        $dotenv->load();
    } catch (Exception $e) {
        // En caso de que no exista .env, continuar con valores por defecto
        error_log('Warning: .env file not found, using default values');
    }
}

/**
 * Obtener variable de entorno con valor por defecto
 */
function getEnvVar($key, $default = null) {
    return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
}

/**
 * Configuración de la API
 */
class ApiConfig {
    public static function getGeminiApiKey() {
        $apiKey = getEnvVar('GEMINI_API_KEY');
        
        // Para testing local, permitir valor por defecto
        if (empty($apiKey) || $apiKey === 'gemini_api_key_here') {
            if (php_sapi_name() === 'cli' || getEnvVar('NODE_ENV') === 'development') {
                return 'DEMO_MODE'; // Modo demo para desarrollo
            }
            throw new Exception('GEMINI_API_KEY no está configurada. Revisa tu archivo .env');
        }
        
        return $apiKey;
    }
    
    public static function isDebugMode() {
        return getEnvVar('DEBUG_MODE', 'false') === 'true';
    }
    
    public static function getEnvironment() {
        return getEnvVar('NODE_ENV', 'development');
    }
    
    public static function isProduction() {
        return self::getEnvironment() === 'production';
    }
}

/**
 * Configuración de Rate Limiting
 */
class RateLimitConfig {
    public static function getMaxRequests() {
        return (int) getEnvVar('RATE_LIMIT_MAX_REQUESTS', 50);
    }
    
    public static function getTimeWindow() {
        return (int) getEnvVar('RATE_LIMIT_TIME_WINDOW', 900); // 15 minutos
    }
    
    public static function getDatabasePath() {
        return __DIR__ . '/data/rate_limit.db';
    }
}
?>