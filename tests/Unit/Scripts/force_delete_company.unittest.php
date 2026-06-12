<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class ForceDeleteCompanyUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../scripts/force_delete_company.php');
    }
}
