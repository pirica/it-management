<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class TestRegisterMailUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../../scripts/test_register_mail.php');
    }
}
