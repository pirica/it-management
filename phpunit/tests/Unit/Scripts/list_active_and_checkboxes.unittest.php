<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class ListActiveAndCheckboxesUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../../scripts/list_active_and_checkboxes.php');
    }
}
