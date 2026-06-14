<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class CheckEmployeesClearTableTransactionUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../scripts/check_employees_clear_table_transaction.php');
    }
}
