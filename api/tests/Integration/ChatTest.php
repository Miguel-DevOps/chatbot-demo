<?php
namespace ChatbotDemo\Tests\Integration;

use PHPUnit\Framework\TestCase;

class ChatTest extends TestCase {
    private function getServerUrl(): string
    {
        return $_ENV['TEST_SERVER_URL'] ?? 'http://localhost:8080';
    }
    
    public function testHealthEndpoint() {
        $url = $this->getServerUrl() . '/health.php?plain=1';
        $response = file_get_contents($url);
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
        $result = file_get_contents($url, false, $context);
        $json = json_decode($result, true);
        $this->assertTrue(isset($json['success']) && $json['success'] === true);
        $this->assertNotEmpty($json['response'] ?? '');
    }
}
