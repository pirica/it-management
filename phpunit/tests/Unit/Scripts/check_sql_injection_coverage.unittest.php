<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/ItmScriptCliTestTrait.php';

class CheckSqlInjectionCoverageUnittest extends TestCase
{
    use ItmScriptCliTestTrait;

    public function testCliAuditPassesOnCleanTree(): void
    {
        $result = $this->runRepoScript('scripts/check_sql_injection_coverage.php');
        $this->assertSame(0, $result['exit'], $result['output']);
        $this->assertStringContainsString('SQL injection static check passed', $result['output']);
        $this->assertStringContainsString('Scanned', $result['output']);
    }
}
