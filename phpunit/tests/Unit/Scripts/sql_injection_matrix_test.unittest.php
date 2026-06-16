<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class SqlInjectionMatrixTestUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../../scripts/sql_injection_matrix_test.php');
    }
}
