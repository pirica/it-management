<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class CheckIndexTableComplianceUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../scripts/check_index_table_compliance.php');
    }
}
