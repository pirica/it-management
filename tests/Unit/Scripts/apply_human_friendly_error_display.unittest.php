<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class ApplyHumanFriendlyErrorDisplayUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../scripts/apply_human_friendly_error_display.php');
    }
}
