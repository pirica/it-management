<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class DetectFkDropdownUiRiskUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../scripts/detect_fk_dropdown_ui_risk.php');
    }
}
