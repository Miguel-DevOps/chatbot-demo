<?php
/**
 * Rate Limiting robusto basado en IP con SQLite
 * Archivo: api/RateLimiter.php
 * 
 * SEGURIDAD: Protege contra ataques distribuidos y es escalable
 */

class RateLimiter {
    private $dbPath;
    private $maxRequests;
    private $timeWindow;
    
    public function __construct() {
        $this->dbPath = RateLimitConfig::getDatabasePath();
        $this->maxRequests = RateLimitConfig::getMaxRequests();
        $this->timeWindow = RateLimitConfig::getTimeWindow();
        
        $this->initDatabase();
    }
    
    /**
     * Inicializar base de datos SQLite
     */
    private function initDatabase() {
        // Crear directorio data si no existe
        $dataDir = dirname($this->dbPath);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        
        try {
            $pdo = new PDO("sqlite:" . $this->dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Crear tabla si no existe
            $sql = "CREATE TABLE IF NOT EXISTS rate_limits (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ip_address TEXT NOT NULL,
                request_time INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )";
            $pdo->exec($sql);
            
            // Crear índice para mejorar performance
            $sql = "CREATE INDEX IF NOT EXISTS idx_ip_time ON rate_limits(ip_address, request_time)";
            $pdo->exec($sql);
            
        } catch (PDOException $e) {
            error_log("Error inicializando base de datos de rate limiting: " . $e->getMessage());
            throw new Exception("Error interno del servidor");
        }
    }
    
    /**
     * Obtener IP del cliente de forma segura
     */
    private function getClientIP() {
        // Verificar headers de proxies/balanceadores
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                // Validar que es una IP válida
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        // Fallback a REMOTE_ADDR
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Verificar si el cliente puede hacer más requests
     */
    public function isAllowed() {
        $ip = $this->getClientIP();
        $currentTime = time();
        $windowStart = $currentTime - $this->timeWindow;
        
        try {
            $pdo = new PDO("sqlite:" . $this->dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Limpiar requests antiguos
            $stmt = $pdo->prepare("DELETE FROM rate_limits WHERE request_time < ?");
            $stmt->execute([$windowStart]);
            
            // Contar requests actuales de esta IP
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM rate_limits WHERE ip_address = ? AND request_time >= ?");
            $stmt->execute([$ip, $windowStart]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $currentRequests = $result['count'] ?? 0;
            
            if ($currentRequests >= $this->maxRequests) {
                return false;
            }
            
            // Registrar nuevo request
            $stmt = $pdo->prepare("INSERT INTO rate_limits (ip_address, request_time) VALUES (?, ?)");
            $stmt->execute([$ip, $currentTime]);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Error en rate limiting: " . $e->getMessage());
            // En caso de error, permitir el request (fail-open)
            return true;
        }
    }
    
    /**
     * Obtener información de rate limiting para headers
     */
    public function getRateLimitInfo() {
        $ip = $this->getClientIP();
        $currentTime = time();
        $windowStart = $currentTime - $this->timeWindow;
        
        try {
            $pdo = new PDO("sqlite:" . $this->dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM rate_limits WHERE ip_address = ? AND request_time >= ?");
            $stmt->execute([$ip, $windowStart]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $currentRequests = $result['count'] ?? 0;
            $remaining = max(0, $this->maxRequests - $currentRequests);
            
            return [
                'limit' => $this->maxRequests,
                'remaining' => $remaining,
                'reset' => $currentTime + $this->timeWindow,
                'window' => $this->timeWindow
            ];
            
        } catch (PDOException $e) {
            error_log("Error obteniendo info de rate limiting: " . $e->getMessage());
            return [
                'limit' => $this->maxRequests,
                'remaining' => $this->maxRequests,
                'reset' => $currentTime + $this->timeWindow,
                'window' => $this->timeWindow
            ];
        }
    }
}
?>