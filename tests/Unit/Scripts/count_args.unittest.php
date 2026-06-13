<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class CountArgsUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../scripts/count_args.php');
    }
}
