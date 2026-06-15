<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class IdentifyModulesUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../scripts/identify_modules.php');
    }
}
