<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class NormalizeDatabaseSqlCreatedAtUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../../scripts/normalize_database_sql_created_at.php');
    }
}
