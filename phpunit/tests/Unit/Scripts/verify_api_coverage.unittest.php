<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class VerifyApiCoverageUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../../scripts/verify_api_coverage.php');
    }
}
