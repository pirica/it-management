<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class ApplyFormFailedSaveDisplayFixUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../scripts/apply_form_failed_save_display_fix.php');
    }
}
