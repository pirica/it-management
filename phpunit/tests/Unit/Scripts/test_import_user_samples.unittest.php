<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class TestImportUserSamplesUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../../scripts/test_import_user_samples.php');
    }
}
