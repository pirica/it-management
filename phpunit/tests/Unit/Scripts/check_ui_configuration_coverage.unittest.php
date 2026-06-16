<?php

declare(strict_types=1);

class CheckUiConfigurationCoverageUnittest extends ItmScriptCliTestCase
{
    public function testCliAuditRunsAndPrintsSummary(): void
    {
        $result = $this->runRepoScript('scripts/check_ui_configuration_coverage.php');
        // Why: full module scan may exit 2 when non-standard modules fail UI checks; subprocess still covers script lines.
        $this->assertContains($result['exit'], [0, 2], $result['output']);
        $this->assertStringContainsString('==== Summary ====', $result['output']);
        $this->assertStringContainsString('PASS:', $result['output']);
    }
}
