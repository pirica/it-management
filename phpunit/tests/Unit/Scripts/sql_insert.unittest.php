<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class SqlInsertUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../../scripts/sql_insert.php');
    }
}
