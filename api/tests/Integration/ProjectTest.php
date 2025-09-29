<?php
namespace ChatbotDemo\Tests\Integration;

use PHPUnit\Framework\TestCase;

class ProjectTest extends TestCase {
    private function getServerUrl(): string
    {
        return $_ENV['TEST_SERVER_URL'] ?? 'http://localhost:8080';
    }
    
    public function testHealthEndpoint() {
        $url = $this->getServerUrl() . '/health.php?plain=1';
        $response = @file_get_contents($url);
        $this->assertNotFalse($response, 'No se pudo conectar a health.php');
        $this->assertStringContainsString('OK', $response);
    }

    public function testChatEndpoint() {
        $data = ["message" => "Hola"];
        $options = [
            'http' => [
                'method'  => 'POST',
                'header'  => 'Content-type: application/json',
                'content' => json_encode($data)
            ]
        ];
        $context  = stream_context_create($options);
        $url = $this->getServerUrl() . '/chat.php';
        $result = @file_get_contents($url, false, $context);
        $this->assertNotFalse($result, 'No se pudo conectar a chat.php');
        $json = json_decode($result, true);
        $this->assertTrue(isset($json['success']) && $json['success'] === true, 'Respuesta no exitosa');
        $this->assertNotEmpty($json['response'] ?? '', 'Respuesta vacía del chatbot');
    }

    public function testApiFilesExist() {
        $apiDir = dirname(__DIR__, 2);
        $files = ['public/index.php', 'src/Controllers/ChatController.php', 'src/Controllers/HealthController.php'];
        foreach ($files as $file) {
            $this->assertFileExists($apiDir . '/' . $file, "Falta el archivo $file en la estructura del proyecto");
        }
    }

    public function testKnowledgeDirectoryExists() {
        $apiDir = dirname(__DIR__, 2);
        $knowledgeDir = $apiDir . '/knowledge';
        $this->assertDirectoryExists($knowledgeDir, 'El directorio de knowledge base no existe');
        
        // Verificar que tiene al menos un archivo
        $files = glob($knowledgeDir . '/*.md');
        $this->assertNotEmpty($files, 'No hay archivos de conocimiento en el directorio');
    }

    public function testFrontendBuildExists() {
        $projectRoot = dirname(__DIR__, 3);
        $distFile = $projectRoot . '/dist/index.html';
        $this->assertFileExists($distFile, 'No existe el build de frontend (dist/index.html)');
    }
}
