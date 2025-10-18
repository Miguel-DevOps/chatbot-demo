# AppConfig Refactor: Remove Singleton Pattern

## Changes Made

### ✅ Removed Singleton Pattern
- Converted the constructor from `private` to `public`
- Removed the static method `getInstance()`
- Removed the static property `$instance`

### ✅ Dependency Injection
`AppConfig` is now registered in the DI container as a normal dependency:

```php
// In DependencyContainer.php
AppConfig::class => function () {
    return new AppConfig();
},
```

### ✅ Standardized Access to Environment Variables
- Private `getEnv()` method uses `$_ENV` as priority over `$_SERVER`
- All configurations consistently use the `getEnv()` method
- Improved readability and maintainability


## Before vs After

### ❌ Before (Singleton)
```php
class SomeService {
    private AppConfig $config;
    
    public function __construct() {
        $this->config = AppConfig::getInstance(); // Global state
    }
}
```

### ✅ After (Dependency Injection)
```php
class SomeService {
    private AppConfig $config;
    
    public function __construct(AppConfig $config) {
        $this->config = $config; // Dependency injected
    }
}
```

## Benefits

1. **Testability**: Easy to create instances with custom configurations for testing
2. **Reduced Coupling**: No shared global state
3. **Flexibility**: Possibility of multiple configurations if necessary
4. **SOLID Principles**: Dependency inversion implemented correctly

## Testing Methods

### Create configuration from array
```php
$config = AppConfig::createFromArray([
    ‘test’ => [‘value’ => ‘test_data’]
]);
```

### Create configuration with specific environment variables
```php
$config = AppConfig::createWithEnv([
    ‘REDIS_HOST’ => ‘test-redis’
]);
```

## Compatibility

- ✅ All controllers, services, and middleware already use injection
- ✅ Tests updated to use the new structure
- ✅ Healthcheck script updated
- ✅ DI container optimized for production with compilation

## Modified Files

- `api/src/Config/AppConfig.php` - Removed singleton, public constructor
- `api/src/Config/DependencyContainer.php` - Registration as normal dependency
- `api/scripts/healthcheck.php` - Use of `new AppConfig()`
- `api/tests/Unit/Simple/AppConfigTest.php` - Updated tests