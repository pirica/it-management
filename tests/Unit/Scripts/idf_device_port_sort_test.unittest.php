<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class IdfDevicePortSortTestUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../scripts/idf_device_port_sort_test.php');
    }
}
