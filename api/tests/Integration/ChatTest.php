<?php
namespace ChatbotDemo\Tests\Integration;

class ChatTest extends IntegrationTestCase {
    
    public function testHealthEndpoint() {
        // Usar helper get() en lugar de file_get_contents
        $response = $this->get('/health.php?plain=1');
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $body = (string) $response->getBody();
        $this->assertStringContainsString('OK', $body);
    }

    public function testChatEndpoint() {
        // Usar helper postJson() en lugar de file_get_contents  
        $response = $this->postJson('/chat.php', ['message' => 'Hola']);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $json = $this->getJsonResponse($response);
        $this->assertTrue(isset($json['success']) && $json['success'] === true);
        $this->assertNotEmpty($json['response'] ?? '');
    }
}
