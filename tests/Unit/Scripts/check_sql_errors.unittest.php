<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class CheckSqlErrorsUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../scripts/check_sql_errors.php');
    }
}
