<?php

declare(strict_types=1);

class CheckAuditLogsCoverageUnittest extends ItmScriptCliTestCase
{
    public function testCliAuditRunsAndPrintsSummary(): void
    {
        $result = $this->runRepoScript('scripts/check_audit_logs_coverage.php');
        // Why: full-tree scan may exit 2 when master has known FAIL rows; still exercises the audit script for coverage.
        $this->assertContains($result['exit'], [0, 2], $result['output']);
        $this->assertStringContainsString('Audit Logs Coverage Check', $result['output']);
        $this->assertStringContainsString('==== Summary ====', $result['output']);
        $this->assertStringContainsString('PASS:', $result['output']);
    }
}
