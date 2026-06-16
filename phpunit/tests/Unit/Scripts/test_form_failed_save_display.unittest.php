<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class TestFormFailedSaveDisplayUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../../scripts/test_form_failed_save_display.php');
    }
}
