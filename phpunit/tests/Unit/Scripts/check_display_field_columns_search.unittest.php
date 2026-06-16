<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/ItmScriptCliTestTrait.php';

class CheckDisplayFieldColumnsSearchUnittest extends TestCase
{
    use ItmScriptCliTestTrait;

    public function testCliAuditPassesOnCleanTree(): void
    {
        $result = $this->runRepoScript('scripts/check_display_field_columns_search.php');
        $this->assertSame(0, $result['exit'], $result['output']);
        $this->assertStringContainsString('PASS: All module index.php files assign $displayFieldColumns', $result['output']);
    }
}
