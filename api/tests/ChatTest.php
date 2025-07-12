<?php
use PHPUnit\Framework\TestCase;

class ChatTest extends TestCase {
    public function testHealthEndpoint() {
        $response = file_get_contents('http://localhost/api/health.php?plain=1');
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
        $result = file_get_contents('http://localhost/api/chat.php', false, $context);
        $json = json_decode($result, true);
        $this->assertTrue(isset($json['success']) && $json['success'] === true);
        $this->assertNotEmpty($json['response'] ?? '');
    }
}
