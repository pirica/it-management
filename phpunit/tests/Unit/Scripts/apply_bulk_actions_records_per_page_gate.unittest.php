<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class ApplyBulkActionsRecordsPerPageGateUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../../scripts/apply_bulk_actions_records_per_page_gate.php');
    }
}
