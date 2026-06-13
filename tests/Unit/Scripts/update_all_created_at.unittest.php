<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class UpdateAllCreatedAtUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../scripts/update_all_created_at.php');
    }
}
