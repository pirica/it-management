<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class CleanupEquipmentTestModuleArtifactsUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../../scripts/cleanup_equipment_test_module_artifacts.php');
    }
}
