<?php
namespace ChatbotDemo\Tests\Unit\Simple;

use PHPUnit\Framework\TestCase;
use ChatbotDemo\Config\AppConfig;

/**
 * Tests for AppConfig dependency injection refactor
 * Verifies that AppConfig works without singleton pattern
 */
class AppConfigTest extends TestCase
{
    public function testAppConfigInstantiation(): void
    {
        $config = new AppConfig();
        $this->assertInstanceOf(AppConfig::class, $config);
    }

    public function testAppConfigGetMethod(): void
    {
        $config = new AppConfig();
        
        // Test with default value
        $defaultValue = $config->get('non.existing.key', 'default');
        $this->assertEquals('default', $defaultValue);
        
        // Test with null default value
        $nullValue = $config->get('non.existing.key');
        $this->assertNull($nullValue);
    }

    public function testAppConfigEnvironmentLoading(): void
    {
        $config = new AppConfig();
        
        // Verify that configuration can be instantiated without errors
        $this->assertIsObject($config);
        
        // Basic test of existing methods
        $this->assertTrue(method_exists($config, 'get'));
        $this->assertTrue(method_exists($config, 'getGeminiApiKey'));
    }

    public function testMultipleInstancesAreIndependent(): void
    {
        $config1 = new AppConfig();
        $config2 = new AppConfig();
        
        // Instances should be different objects (no singleton)
        $this->assertNotSame($config1, $config2);
        
        // But should have same configuration values
        $this->assertEquals($config1->get('app.name'), $config2->get('app.name'));
    }

    public function testCreateFromArrayForTesting(): void
    {
        $testConfig = [
            'test' => [
                'value' => 'test_data'
            ]
        ];
        
        $config = AppConfig::createFromArray($testConfig);
        $this->assertEquals('test_data', $config->get('test.value'));
    }

    public function testCreateWithEnvForTesting(): void
    {
        $testEnv = [
            'TEST_VAR' => 'test_value'
        ];
        
        $config = AppConfig::createWithEnv($testEnv);
        $this->assertInstanceOf(AppConfig::class, $config);
        
        // Should not affect global $_ENV
        $this->assertArrayNotHasKey('TEST_VAR', $_ENV);
    }
}