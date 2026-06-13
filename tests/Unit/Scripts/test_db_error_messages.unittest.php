<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class TestDbErrorMessagesUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../scripts/test_db_error_messages.php');
    }
}
