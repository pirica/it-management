<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class CheckEquipmentClearTableDeleteUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../scripts/check_equipment_clear_table_delete.php');
    }
}
