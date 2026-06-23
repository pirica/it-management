<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class LibUtf8FileUnittest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('ITM_CLI_SCRIPT')) {
            define('ITM_CLI_SCRIPT', true);
        }
        require_once __DIR__ . '/../../../../scripts/lib/utf8_file.php';
    }

    public function testWriteWithoutBom(): void
    {
        $path = sys_get_temp_dir() . '/itm_utf8_nobom_' . uniqid('', true) . '.txt';
        try {
            $this->assertTrue(itm_write_utf8_text_file($path, "plain\n", false));
            $raw = (string)file_get_contents($path);
            $this->assertStringStartsWith('plain', $raw);
            $this->assertSame("plain\n", itm_read_utf8_text_file($path));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testReadMissingFileReturnsEmptyString(): void
    {
        $this->assertSame('', itm_read_utf8_text_file(sys_get_temp_dir() . '/itm_missing_' . uniqid('', true) . '.txt'));
    }
}
