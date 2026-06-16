<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class LibMbqaStepDisplayUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../scripts/lib/mbqa_step_display.php');
    }
}
