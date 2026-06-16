<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class TestEmailForgotUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../../scripts/test_email_forgot.php');
    }
}
