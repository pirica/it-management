<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class IdfsApiPayloadDryRunUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../scripts/idfs_api_payload_dry_run.php');
    }
}
