<?php
namespace ChatbotDemo\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Mockery;
use ChatbotDemo\Services\KnowledgeBaseService;
use ChatbotDemo\Repositories\KnowledgeProviderInterface;
use ChatbotDemo\Config\AppConfig;
use Psr\Log\LoggerInterface;

class KnowledgeBaseServiceTest extends TestCase
{
    private KnowledgeBaseService $knowledgeBaseService;
    private string $testKnowledgeDir;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create temporary directory for tests FIRST
        $this->testKnowledgeDir = sys_get_temp_dir() . '/kb_test_' . uniqid();
        mkdir($this->testKnowledgeDir, 0777, true);
        
        // Mock configuration
        $mockConfig = Mockery::mock(AppConfig::class);
        $mockConfig->shouldReceive('get')
            ->with('knowledge.path')
            ->andReturn($this->testKnowledgeDir);
        $mockConfig->shouldReceive('get')
            ->with('knowledge_base.path')
            ->andReturn($this->testKnowledgeDir);
        $mockConfig->shouldReceive('get')
            ->with('knowledge_base.cache_enabled', true)
            ->andReturn(true);
        $mockConfig->shouldReceive('get')
            ->with('knowledge_base.cache_ttl', 3600)
            ->andReturn(3600);
        
        // Mock the logger
        $mockLogger = Mockery::mock(LoggerInterface::class);
        $mockLogger->shouldReceive('info')->byDefault();
        $mockLogger->shouldReceive('debug')->byDefault();
        $mockLogger->shouldReceive('warning')->byDefault();
        $mockLogger->shouldReceive('error')->byDefault();
        
        // Mock the knowledge provider
        $mockKnowledgeProvider = Mockery::mock(KnowledgeProviderInterface::class);
        $mockKnowledgeProvider->shouldReceive('loadKnowledge')
            ->byDefault()
            ->andReturn("# Servicios\nOfrecemos consultoría estratégica y desarrollo de software.\n\n# Precios\nNuestros precios son competitivos en el mercado.");
        $mockKnowledgeProvider->shouldReceive('getKnowledge')
            ->byDefault()
            ->andReturn("# Servicios\nOfrecemos consultoría estratégica y desarrollo de software.\n\n# Precios\nNuestros precios son competitivos en el mercado.");
        
        // Create test knowledge files
        file_put_contents(
            $this->testKnowledgeDir . '/servicios.md',
            "# Servicios\nOfrecemos consultoría estratégica y desarrollo de software."
        );
        
        file_put_contents(
            $this->testKnowledgeDir . '/precios.md',
            "# Precios\nNuestros precios son competitivos en el mercado."
        );
        
        $this->knowledgeBaseService = new KnowledgeBaseService($mockConfig, $mockLogger, $mockKnowledgeProvider);
    }

    protected function tearDown(): void
    {
        // Limpiar archivos temporales
        $this->removeDir($this->testKnowledgeDir);
        Mockery::close();
        parent::tearDown();
    }

    private function removeDir(string $dir): void
    {
        if (is_dir($dir)) {
            $files = array_diff(scandir($dir), ['.', '..']);
            foreach ($files as $file) {
                $path = $dir . DIRECTORY_SEPARATOR . $file;
                is_dir($path) ? $this->removeDir($path) : unlink($path);
            }
            rmdir($dir);
        }
    }

    public function testGetKnowledgeBaseSuccess(): void
    {
        // Act
        $content = $this->knowledgeBaseService->getKnowledgeBase();

        // Assert
        $this->assertIsString($content);
        $this->assertStringContainsString('consultoría estratégica', $content);
        $this->assertStringContainsString('precios son competitivos', $content);
    }

    public function testAddUserContext(): void
    {
        // Arrange
        $knowledge = "# Servicios\nOfrecemos consultoría estratégica.";
        $userMessage = "¿Qué servicios ofrecen?";

        // Act
        $result = $this->knowledgeBaseService->addUserContext($knowledge, $userMessage);

        // Assert
        $this->assertIsString($result);
        $this->assertStringContainsString($knowledge, $result);
        $this->assertStringContainsString($userMessage, $result);
    }

    public function testInvalidateCache(): void
    {
        // Act & Assert - should not throw exception
        $this->knowledgeBaseService->invalidateCache();
        $this->assertTrue(true); // If we get here, no exception was thrown
    }
}