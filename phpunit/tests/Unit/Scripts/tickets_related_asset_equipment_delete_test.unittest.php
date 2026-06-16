<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class TicketsRelatedAssetEquipmentDeleteTestUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../../scripts/tickets_related_asset_equipment_delete_test.php');
    }
}
