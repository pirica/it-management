<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class EquipmentDeleteClearTableTestUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../../scripts/equipment_delete_clear_table_test.php');
    }
}
