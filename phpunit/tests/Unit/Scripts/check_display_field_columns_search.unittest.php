<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class CheckDisplayFieldColumnsSearchUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../../scripts/check_display_field_columns_search.php');
    }
}
