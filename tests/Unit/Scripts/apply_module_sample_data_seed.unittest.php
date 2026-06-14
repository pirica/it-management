<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class ApplyModuleSampleDataSeedUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../scripts/apply_module_sample_data_seed.php');
    }
}
