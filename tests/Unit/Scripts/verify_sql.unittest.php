<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class VerifySqlUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../scripts/verify_sql.php');
    }
}
