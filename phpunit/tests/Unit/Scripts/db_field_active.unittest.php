<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class DbFieldActiveUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../../scripts/db_field_active.php');
    }
}
