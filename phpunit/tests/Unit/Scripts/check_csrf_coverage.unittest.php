<?php

declare(strict_types=1);

class CheckCsrfCoverageUnittest extends ItmScriptCliTestCase
{
    public function testCliAuditPassesOnCleanTree(): void
    {
        $result = $this->runRepoScript('scripts/check_csrf_coverage.php');
        $this->assertSame(0, $result['exit'], $result['output']);
        $this->assertStringContainsString('CSRF coverage check passed', $result['output']);
        $this->assertStringContainsString('Scanned', $result['output']);
    }
}
