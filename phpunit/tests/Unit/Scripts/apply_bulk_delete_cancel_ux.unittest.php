<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class ApplyBulkDeleteCancelUxUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../../scripts/apply_bulk_delete_cancel_ux.php');
    }
}
