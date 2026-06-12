<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class EmployeesDeleteClearTableTestUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../scripts/employees_delete_clear_table_test.php');
    }
}
