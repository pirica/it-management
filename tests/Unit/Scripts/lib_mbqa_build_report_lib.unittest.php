<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class LibMbqaBuildReportLibUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../scripts/lib/mbqa_build_report_lib.php');
    }
}
