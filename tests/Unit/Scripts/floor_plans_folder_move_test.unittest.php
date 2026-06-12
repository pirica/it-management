<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class FloorPlansFolderMoveTestUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../scripts/floor_plans_folder_move_test.php');
    }
}
