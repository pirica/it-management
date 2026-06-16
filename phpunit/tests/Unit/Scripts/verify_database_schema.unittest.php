<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class VerifyDatabaseSchemaUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../../scripts/verify_database_schema.php');
    }
}
