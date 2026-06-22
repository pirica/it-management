<?php

declare(strict_types=1);

class CheckDisplayFieldColumnsSearchUnittest extends ItmScriptCliTestCase
{
    public function testCliAuditPassesOnCleanTree(): void
    {
        $result = $this->runRepoScript('scripts/check_display_field_columns_search.php');
        $this->assertSame(0, $result['exit'], $result['output']);
        $this->assertStringContainsString('PASS: All module index.php files assign $displayFieldColumns', $result['output']);
    }
}
