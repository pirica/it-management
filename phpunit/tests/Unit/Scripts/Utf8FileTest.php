<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class Utf8FileTest extends TestCase
{
    private string $testFile;

    protected function setUp(): void
    {
        if (!defined('ITM_CLI_SCRIPT')) {
            define('ITM_CLI_SCRIPT', true);
        }
        require_once __DIR__ . '/../../../../scripts/lib/utf8_file.php';
        $this->testFile = __DIR__ . '/test_utf8_file.txt';
    }

    protected function tearDown(): void
    {
        if (is_file($this->testFile)) {
            unlink($this->testFile);
        }
    }

    public function testWriteAndReadUtf8WithoutBom(): void
    {
        $content = "Hello, World! 🌍";
        $success = itm_write_utf8_text_file($this->testFile, $content, false);
        $this->assertTrue($success);

        $readContent = itm_read_utf8_text_file($this->testFile);
        $this->assertEquals($content, $readContent);

        // Verify no BOM
        $raw = file_get_contents($this->testFile);
        $this->assertStringStartsNotWith("\xEF\xBB\xBF", $raw);
    }

    public function testWriteAndReadUtf8WithBom(): void
    {
        $content = "UTF-8 with BOM";
        $success = itm_write_utf8_text_file($this->testFile, $content, true);
        $this->assertTrue($success);

        $readContent = itm_read_utf8_text_file($this->testFile);
        $this->assertEquals($content, $readContent);

        // Verify BOM is present in raw file
        $raw = file_get_contents($this->testFile);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $raw);
    }

    public function testReadNonExistentFileReturnsEmptyString(): void
    {
        $this->assertEquals('', itm_read_utf8_text_file('non_existent.txt'));
    }
}
