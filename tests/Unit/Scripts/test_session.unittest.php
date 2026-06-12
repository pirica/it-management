<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class TestSessionUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../scripts/test_session.php');
    }
}
