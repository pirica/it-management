<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class ListModulesNotOnSidebarUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../scripts/list_modules_not_on_sidebar.php');
    }
}
