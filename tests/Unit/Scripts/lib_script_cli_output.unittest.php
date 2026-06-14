<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class LibScriptCliOutputUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../scripts/lib/script_cli_output.php');
    }
}
