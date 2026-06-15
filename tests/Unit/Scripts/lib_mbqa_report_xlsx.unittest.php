<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class LibMbqaReportXlsxUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../scripts/lib/mbqa_report_xlsx.php');
    }
}
