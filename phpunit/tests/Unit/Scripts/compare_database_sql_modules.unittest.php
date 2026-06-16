<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class CompareDatabaseSqlModulesUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../../scripts/compare_database_sql_modules.php');
    }
}
