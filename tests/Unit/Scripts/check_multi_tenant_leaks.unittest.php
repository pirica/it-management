<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class CheckMultiTenantLeaksUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../scripts/check_multi_tenant_leaks.php');
    }
}
