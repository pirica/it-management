<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class ExportFloorPlanFoldersSeedUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../scripts/export_floor_plan_folders_seed.php');
    }
}
