<?php

declare(strict_types=1);

class CheckScriptDisposableUsersUnittest extends ItmScriptCliTestCase
{
    public function testCheckScriptDisposableUsersPasses(): void
    {
        $result = $this->runRepoScript('scripts/check_script_disposable_users.php');
        $this->assertSame(0, $result['exit'], $result['output']);
        $this->assertStringContainsString('PASS:', $result['output']);
    }
}
