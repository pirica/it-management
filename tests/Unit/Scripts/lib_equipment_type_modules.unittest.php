<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class LibEquipmentTypeModulesUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../scripts/lib/equipment_type_modules.php');
    }
}
