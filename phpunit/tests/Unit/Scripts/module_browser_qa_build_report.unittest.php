<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class ModuleBrowserQaBuildReportUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../../scripts/module_browser_qa_build_report.php');
    }
}
