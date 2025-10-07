<?php
namespace ChatbotDemo\Tests\Unit\Simple;

use PHPUnit\Framework\TestCase;

/**
 * Basic tests to verify that the framework works
 */
class BasicTest extends TestCase
{
    public function testBasicAssertion(): void
    {
        $this->assertTrue(true);
        $this->assertEquals(2, 1 + 1);
        $this->assertIsString('hello world');
    }

    public function testArrayOperations(): void
    {
        $array = ['a', 'b', 'c'];
        $this->assertCount(3, $array);
        $this->assertContains('b', $array);
        $this->assertArrayHasKey(0, $array);
    }

    public function testStringOperations(): void
    {
        $text = "Hello World";
        $this->assertStringContainsString("World", $text);
        $this->assertEquals(11, strlen($text));
        $this->assertEquals("HELLO WORLD", strtoupper($text));
    }
}