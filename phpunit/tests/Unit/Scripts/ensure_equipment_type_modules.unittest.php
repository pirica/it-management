<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class EnsureEquipmentTypeModulesUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../../scripts/ensure_equipment_type_modules.php');
    }
}
