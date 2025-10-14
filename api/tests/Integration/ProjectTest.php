<?php
namespace ChatbotDemo\Tests\Integration;

class ProjectTest extends IntegrationTestCase {
    
    public function testHealthEndpoint() {
        // Usar helper get() en lugar de file_get_contents
        $response = $this->get('/health.php?plain=1');
        
        $this->assertEquals(200, $response->getStatusCode(), 'No se pudo conectar a health.php');
        
        $body = (string) $response->getBody();
        $this->assertStringContainsString('OK', $body);
    }

    public function testChatEndpoint() {
        // Usar helper postJson() en lugar de file_get_contents
        $response = $this->postJson('/chat.php', ['message' => 'Hola']);
        
        $this->assertEquals(200, $response->getStatusCode(), 'No se pudo conectar a chat.php');
        
        $json = $this->getJsonResponse($response);
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
        $this->markTestSkipped('Frontend build not required for backend testing pipeline');
        
        $projectRoot = dirname(__DIR__, 3);
        $distFile = $projectRoot . '/dist/index.html';
        $this->assertFileExists($distFile, 'No existe el build de frontend (dist/index.html)');
    }
}
