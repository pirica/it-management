<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class CheckSqlInjectionCoverageUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../scripts/check_sql_injection_coverage.php');
    }
}
