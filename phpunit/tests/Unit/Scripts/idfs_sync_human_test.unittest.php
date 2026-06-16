<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class IdfsSyncHumanTestUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../../scripts/idfs_sync_human_test.php');
    }
}
