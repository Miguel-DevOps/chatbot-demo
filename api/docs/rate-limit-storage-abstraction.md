# Rate Limiting Storage Abstraction

This document explains how the storage abstraction for Rate Limiting works and how to switch between different implementations.

## Architecture

The abstraction is based on the **Strategy pattern** and the **Dependency Inversion Principle**:

```
RateLimitService
    ↓ (depends on)
RateLimitStorageInterface
    ↑ (implemented by)
SqliteRateLimitStorage | RedisRateLimitStorage | ...
```

## Components

### 1\. `RateLimitStorageInterface`

Defines the contract that any storage implementation must adhere to:

```php
interface RateLimitStorageInterface
{
    public function getRequestsCount(string $ip, int $windowStart): int;
    public function logRequest(string $ip, int $timestamp): void;
    public function cleanupExpiredRequests(int $windowStart): int;
    public function isHealthy(): bool;
    public function getStats(): array;
}
```

### 2\. Available Implementations

  - **`SqliteRateLimitStorage`**: Implementation using SQLite (default)
  - **`RedisRateLimitStorage`**: Implementation using Redis (example)

### 3\. `RateLimitService`

Uses the abstraction without knowing the specific implementation:

```php
class RateLimitService
{
    public function __construct(
        AppConfig $config,
        LoggerInterface $logger,
        RateLimitStorageInterface $storage  // ← Abstraction
    ) {
        // ...
    }
}
```

## How to Switch from SQLite to Redis

### Step 1: Install Redis (if using RedisRateLimitStorage)

```bash
# Ubuntu/Debian
sudo apt-get install redis-server php-redis

# CentOS/RHEL
sudo yum install redis php-pecl-redis

# macOS
brew install redis
pecl install redis
```

### Step 2: Configure Redis (optional)

Add Redis configuration in `AppConfig.php` or environment variables:

```php
'redis' => [
    'host' => $this->getEnv('REDIS_HOST', '127.0.0.1'),
    'port' => (int) $this->getEnv('REDIS_PORT', '6379'),
    'timeout' => (int) $this->getEnv('REDIS_TIMEOUT', '5'),
    'database' => (int) $this->getEnv('REDIS_DATABASE', '0'),
]
```

### Step 3: Change the Dependency Injection

In `DependencyContainer.php`, change a single line:

```php
// BEFORE (SQLite)
RateLimitStorageInterface::class => function (AppConfig $config, LoggerInterface $logger) {
    return new SqliteRateLimitStorage($config, $logger);
},

// AFTER (Redis)
RateLimitStorageInterface::class => function (AppConfig $config, LoggerInterface $logger) {
    return new RedisRateLimitStorage($config, $logger);
},
```

### Step 4: Verify the Change

Check the health endpoint to verify that Redis is working:

```bash
curl http://localhost:8000/health | jq '.checks.rate_limit_storage'
```

## Benefits of Abstraction

### ✅ **Interchangeability**

  - Switching from SQLite to Redis requires modifying only 1 line of code
  - No changes to the business logic

### ✅ **Testability**

  - Easy to create mocks for testing
  - Unit tests are independent of the storage

### ✅ **Extensibility**

  - Add new implementations (MySQL, MongoDB, etc.) without touching existing code

### ✅ **Monitoring**

  - Consistent statistics and health checks
  - Implementation-specific metrics

## Custom Implementations

To create a new implementation (e.g., MySQL):

### 1\. Create the Class

```php
class MysqlRateLimitStorage implements RateLimitStorageInterface
{
    public function getRequestsCount(string $ip, int $windowStart): int
    {
        // MySQL-specific implementation
    }
    
    // ... other methods
}
```

### 2\. Register in the Container

```php
RateLimitStorageInterface::class => function (AppConfig $config, LoggerInterface $logger) {
    return new MysqlRateLimitStorage($config, $logger);
},
```

### 3\. Configure (if necessary)

Add specific configuration in `AppConfig.php`.

## Applied Design Patterns

### Strategy Pattern

  - `RateLimitStorageInterface` defines the strategy
  - Different implementations provide specific algorithms

### Dependency Injection

  - `RateLimitService` receives the dependency via the constructor
  - It does not create instances directly

### Factory Pattern (in DependencyContainer)

  - The container acts as a factory
  - Centralizes object creation

## Use Cases by Implementation

### SQLite (`SqliteRateLimitStorage`)

  - **✅ Ideal for**: Development, testing, small-scale applications
  - **✅ Advantages**: No external dependencies, simple configuration
  - **❌ Limitations**: Not distributable, lower performance

### Redis (`RedisRateLimitStorage`)

  - **✅ Ideal for**: Production, multiple instances, high concurrency
  - **✅ Advantages**: Excellent performance, distributable, automatic TTL
  - **❌ Limitations**: External dependency, higher complexity

### MySQL/PostgreSQL (future implementation)

  - **✅ Ideal for**: When you already have a relational DB, detailed auditing
  - **✅ Advantages**: Guaranteed persistence, complex queries
  - **❌ Limitations**: Higher latency, more resources

## Monitoring and Debugging

### Health Check

```bash
curl http://localhost:8000/health
```

### Storage Statistics

```php
$stats = $rateLimitService->getStorageStats();
// Specific information for each implementation
```

### Logs

Each implementation logs important events:

  - Successful/failed connections
  - Cleanup operations
  - Storage errors

## Testing

### Abstraction Test

```bash
# Run tests that verify the abstraction
./vendor/bin/phpunit tests/Unit/Services/RateLimitStorageAbstractionTest.php
```

### Specific Implementation Tests

```bash
# SQLite tests
./vendor/bin/phpunit --filter SqliteRateLimitStorage

# Redis tests (requires a running Redis instance)
./vendor/bin/phpunit --filter RedisRateLimitStorage
```

## Conclusion

This abstraction demonstrates **evolutionary architecture** in action:

1.  **Separation of concerns**: Business logic vs. persistence
2.  **Open/Closed Principle**: Open for extension, closed for modification
3.  **Dependency Inversion**: Depends on abstractions, not implementations

The result is a system that can evolve from SQLite in development to Redis in production, **without changing a single line of the Rate Limiting logic**.