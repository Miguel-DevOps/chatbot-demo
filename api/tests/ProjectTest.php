<?php
use PHPUnit\Framework\TestCase;

class ProjectTest extends TestCase {
    public function testHealthEndpoint() {
        $response = @file_get_contents('http://localhost/api/health.php?plain=1');
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
        $result = @file_get_contents('http://localhost/api/chat.php', false, $context);
        $this->assertNotFalse($result, 'No se pudo conectar a chat.php');
        $json = json_decode($result, true);
        $this->assertTrue(isset($json['success']) && $json['success'] === true, 'Respuesta no exitosa');
        $this->assertNotEmpty($json['response'] ?? '', 'Respuesta vacía del chatbot');
    }

    public function testKnowledgeBase() {
        $kb = include __DIR__ . '/../knowledge-base.php';
        $this->assertIsString($kb, 'La knowledge base debe ser un string');
        $this->assertNotEmpty($kb, 'La knowledge base está vacía');
    }

    public function testApiFilesExist() {
        $files = ['chat.php', 'health.php', 'knowledge-base.php'];
        foreach ($files as $file) {
            $this->assertFileExists(__DIR__ . '/../' . $file, "Falta el archivo $file en api/");
        }
    }

    public function testFrontendBuild() {
        $dist = getcwd() . '/dist/index.html';
        $this->assertFileExists($dist, 'No existe el build de frontend (dist/index.html)');
    }
}
