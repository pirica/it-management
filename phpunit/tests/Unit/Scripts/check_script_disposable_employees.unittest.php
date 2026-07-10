<?php

declare(strict_types=1);

class CheckScriptDisposableEmployeesUnittest extends ItmScriptCliTestCase
{
    public function testCheckScriptDisposableEmployeesPasses(): void
    {
        $result = $this->runRepoScript('scripts/check_script_disposable_employees.php');
        $this->assertSame(0, $result['exit'], $result['output']);
        $this->assertStringContainsString('PASS:', $result['output']);
    }
}
