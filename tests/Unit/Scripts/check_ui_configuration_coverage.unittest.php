<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class CheckUiConfigurationCoverageUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../scripts/check_ui_configuration_coverage.php');
    }
}
