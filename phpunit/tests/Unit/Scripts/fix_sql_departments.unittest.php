<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class FixSqlDepartmentsUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../../scripts/fix_sql_departments.php');
    }
}
