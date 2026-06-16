<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class ModuleCleanTestsQaRunnerUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../../scripts/module_clean_tests_qa_runner.php');
    }
}
