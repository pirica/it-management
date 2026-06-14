<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class FixSqlBroadUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../scripts/fix_sql_broad.php');
    }
}
