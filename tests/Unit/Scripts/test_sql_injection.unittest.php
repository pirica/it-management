<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class TestSqlInjectionUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../scripts/test_sql_injection.php');
    }
}
