<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class CheckCsrfCoverageUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../../scripts/check_csrf_coverage.php');
    }
}
