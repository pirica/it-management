<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class ApplyDisplayFieldColumnsSearchAliasUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../../scripts/apply_display_field_columns_search_alias.php');
    }
}
