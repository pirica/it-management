<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class RepairTableFromSchemaUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../../scripts/repair_table_from_schema.php');
    }
}
