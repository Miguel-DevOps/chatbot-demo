<?php
namespace ChatbotDemo\Tests\Unit\Simple;

use PHPUnit\Framework\TestCase;
use ChatbotDemo\Config\AppConfig;

/**
 * Test básico para verificar que AppConfig funciona
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
        
        // Test con valor por defecto
        $defaultValue = $config->get('non.existing.key', 'default');
        $this->assertEquals('default', $defaultValue);
        
        // Test con valor null por defecto
        $nullValue = $config->get('non.existing.key');
        $this->assertNull($nullValue);
    }

    public function testAppConfigEnvironmentLoading(): void
    {
        $config = AppConfig::getInstance();
        
        // Verificar que la configuración se puede instanciar sin errores
        $this->assertIsObject($config);
        
        // Test básico de método existente
        $this->assertTrue(method_exists($config, 'get'));
        $this->assertTrue(method_exists($config, 'getGeminiApiKey'));
    }
}