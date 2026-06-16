<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class LibUtf8FileUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../../scripts/lib/utf8_file.php');
    }
}
