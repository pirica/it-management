<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class LibSqlInjectionDetectorUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../scripts/lib/sql_injection_detector.php');
    }
}
