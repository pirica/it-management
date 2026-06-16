<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class ExplorerHumanTestUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../../scripts/explorer_human_test.php');
    }
}
