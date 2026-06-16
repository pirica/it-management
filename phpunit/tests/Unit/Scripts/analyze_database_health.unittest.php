<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class AnalyzeDatabaseHealthUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../../scripts/analyze_database_health.php');
    }
}
