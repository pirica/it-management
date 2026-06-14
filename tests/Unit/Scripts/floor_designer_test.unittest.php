<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class FloorDesignerTestUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../scripts/floor_designer_test.php');
    }
}
