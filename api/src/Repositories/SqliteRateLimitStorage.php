<?php

declare(strict_types=1);

namespace ChatbotDemo\Repositories;

use ChatbotDemo\Config\AppConfig;
use Psr\Log\LoggerInterface;
use PDO;
use PDOException;

/**
 * Implementación de almacenamiento de Rate Limiting usando SQLite
 * 
 * Esta clase encapsula toda la lógica específica de SQLite para el rate limiting,
 * implementando la interfaz RateLimitStorageInterface para permitir intercambio
 * fácil con otras implementaciones como Redis, MySQL, etc.
 */
class SqliteRateLimitStorage implements RateLimitStorageInterface
{
    private AppConfig $config;
    private LoggerInterface $logger;
    private ?PDO $pdo = null;

    public function __construct(AppConfig $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->initializeDatabase();
    }

    /**
     * Inicializa la base de datos SQLite
     */
    private function initializeDatabase(): void
    {
        $dbPath = $this->config->get('rate_limit.database_path');
        $dataDir = dirname($dbPath);
        
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
            $this->logger->info('Created rate limit database directory', ['path' => $dataDir]);
        }

        try {
            $this->pdo = new PDO("sqlite:" . $dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $this->createTables();
            
            $this->logger->info('Rate limit database initialized successfully', ['path' => $dbPath]);
            
        } catch (PDOException $e) {
            $this->logger->error('Failed to initialize rate limit database', [
                'error' => $e->getMessage(),
                'path' => $dbPath
            ]);
            throw $e;
        }
    }

    /**
     * Crea las tablas necesarias en SQLite
     */
    private function createTables(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS rate_limits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip_address TEXT NOT NULL,
            request_time INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
        $this->pdo->exec($sql);
        
        $sql = "CREATE INDEX IF NOT EXISTS idx_ip_time ON rate_limits(ip_address, request_time)";
        $this->pdo->exec($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestsCount(string $ip, int $windowStart): int
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) as count FROM rate_limits WHERE ip_address = ? AND request_time >= ?"
            );
            $stmt->execute([$ip, $windowStart]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $count = $result['count'] ?? 0;
            
            $this->logger->debug('Retrieved requests count from SQLite storage', [
                'ip' => $ip,
                'window_start' => $windowStart,
                'count' => $count
            ]);
            
            return (int) $count;
            
        } catch (PDOException $e) {
            $this->logger->error('Failed to get requests count from SQLite storage', [
                'error' => $e->getMessage(),
                'ip' => $ip,
                'window_start' => $windowStart
            ]);
            
            // Fail-safe: return 0 to allow requests when storage fails
            return 0;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function logRequest(string $ip, int $timestamp): void
    {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO rate_limits (ip_address, request_time) VALUES (?, ?)"
            );
            $stmt->execute([$ip, $timestamp]);
            
            $this->logger->debug('Request logged to SQLite storage', [
                'ip' => $ip,
                'timestamp' => $timestamp
            ]);
            
        } catch (PDOException $e) {
            $this->logger->error('Failed to log request to SQLite storage', [
                'error' => $e->getMessage(),
                'ip' => $ip,
                'timestamp' => $timestamp
            ]);
            
            // Don't throw exception to avoid breaking the request flow
        }
    }

    /**
     * {@inheritdoc}
     */
    public function cleanupExpiredRequests(int $windowStart): int
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM rate_limits WHERE request_time < ?");
            $stmt->execute([$windowStart]);
            $deletedCount = $stmt->rowCount();
            
            $this->logger->debug('Cleaned up expired requests from SQLite storage', [
                'window_start' => $windowStart,
                'deleted_count' => $deletedCount
            ]);
            
            return $deletedCount;
            
        } catch (PDOException $e) {
            $this->logger->error('Failed to cleanup expired requests from SQLite storage', [
                'error' => $e->getMessage(),
                'window_start' => $windowStart
            ]);
            
            return 0;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isHealthy(): bool
    {
        try {
            if ($this->pdo === null) {
                return false;
            }
            
            // Test database connection with a simple query
            $stmt = $this->pdo->query("SELECT 1");
            $result = $stmt->fetch();
            
            return $result !== false;
            
        } catch (PDOException $e) {
            $this->logger->warning('SQLite storage health check failed', [
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getStats(): array
    {
        try {
            $stats = [];
            
            // Total records count
            $stmt = $this->pdo->query("SELECT COUNT(*) as total_records FROM rate_limits");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['total_records'] = $result['total_records'] ?? 0;
            
            // Unique IPs count
            $stmt = $this->pdo->query("SELECT COUNT(DISTINCT ip_address) as unique_ips FROM rate_limits");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['unique_ips'] = $result['unique_ips'] ?? 0;
            
            // Recent requests (last 24 hours)
            $yesterday = time() - 86400;
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as recent_requests FROM rate_limits WHERE request_time >= ?");
            $stmt->execute([$yesterday]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['recent_requests_24h'] = $result['recent_requests'] ?? 0;
            
            // Database file size
            $dbPath = $this->config->get('rate_limit.database_path');
            if (file_exists($dbPath)) {
                $stats['database_size_bytes'] = filesize($dbPath);
                $stats['database_size_human'] = $this->formatBytes(filesize($dbPath));
            }
            
            $stats['storage_type'] = 'sqlite';
            $stats['database_path'] = $dbPath;
            $stats['last_check'] = time();
            
            return $stats;
            
        } catch (PDOException $e) {
            $this->logger->error('Failed to get SQLite storage stats', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'storage_type' => 'sqlite',
                'error' => 'Failed to retrieve stats',
                'last_check' => time()
            ];
        }
    }

    /**
     * Formatea bytes en formato legible
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        
        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }
        
        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * Obtiene la instancia PDO (para casos especiales o testing)
     */
    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }
}