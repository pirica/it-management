<?php

declare(strict_types=1);

class CheckAuditLogsCoverageUnittest extends ItmScriptCliTestCase
{
    public function testCliAuditRunsAndPrintsSummary(): void
    {
        $result = $this->runRepoScript('scripts/check_audit_logs_coverage.php');
        $this->assertSame(0, $result['exit'], $result['output']);
        $this->assertStringContainsString('Audit Logs Coverage Check', $result['output']);
        $this->assertStringContainsString('==== Summary ====', $result['output']);
        $this->assertStringContainsString('PASS:', $result['output']);
    }
}
