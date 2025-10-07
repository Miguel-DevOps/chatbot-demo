<?php
namespace ChatbotDemo\Tests\Unit\Simple;

use PHPUnit\Framework\TestCase;
use ChatbotDemo\Config\AppConfig;

/**
 * Basic test to verify that AppConfig works
 */
class AppConfigTest extends TestCase
{
    public function testAppConfigInstantiation(): void
    {
        $config = AppConfig::getInstance();
        $this->assertInstanceOf(AppConfig::class, $config);
    }

    public function testAppConfigGetMethod(): void
    {
        $config = AppConfig::getInstance();
        
        // Test with default value
        $defaultValue = $config->get('non.existing.key', 'default');
        $this->assertEquals('default', $defaultValue);
        
        // Test with null default value
        $nullValue = $config->get('non.existing.key');
        $this->assertNull($nullValue);
    }

    public function testAppConfigEnvironmentLoading(): void
    {
        $config = AppConfig::getInstance();
        
        // Verify that configuration can be instantiated without errors
        $this->assertIsObject($config);
        
        // Basic test of existing method
        $this->assertTrue(method_exists($config, 'get'));
        $this->assertTrue(method_exists($config, 'getGeminiApiKey'));
    }
}