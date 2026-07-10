<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * CLI regression wrapper for scripts/verify_company_module_access.php.
 *
 * Why: Sidebar discovery probes (registry / table / folder / both / neither) live in the
 * verify script per scripts/SCRIPTS.md; PHPUnit asserts a green subprocess run.
 */
class CompanyModuleAccessVerifyTest extends TestCase
{
    use ItmScriptCliTestTrait;

    public function testVerifyCompanyModuleAccessScriptPasses(): void
    {
        if (getenv('ITM_SKIP_DB_TESTS') === '1') {
            $this->markTestSkipped('Database tests skipped (ITM_SKIP_DB_TESTS=1).');
        }

        $result = $this->runRepoScript('scripts/verify_company_module_access.php');

        $this->assertSame(
            0,
            $result['exit'],
            'verify_company_module_access.php failed: ' . substr($result['output'], 0, 4000)
        );
        $this->assertStringContainsString('[PASS] Registry-only probe', $result['output']);
        $this->assertStringContainsString('[PASS] New MySQL table probe', $result['output']);
        $this->assertStringContainsString('[PASS] Folder-only probe', $result['output']);
        $this->assertStringContainsString('[PASS] Registry + folder probe', $result['output']);
        $this->assertStringContainsString('[PASS] Neither-path probe', $result['output']);
        $this->assertStringContainsString('[PASS] Company module access verification succeeded.', $result['output']);
    }
}
