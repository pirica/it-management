<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class LibScriptBrowserNavUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../../scripts/lib/script_browser_nav.php');
    }
}
