<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class CheckAuditLogsCoverageUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../../scripts/check_audit_logs_coverage.php');
    }
}
