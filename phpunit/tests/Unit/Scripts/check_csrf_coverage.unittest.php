<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/ItmScriptCliTestTrait.php';

class CheckCsrfCoverageUnittest extends TestCase
{
    use ItmScriptCliTestTrait;

    public function testCliAuditPassesOnCleanTree(): void
    {
        $result = $this->runRepoScript('scripts/check_csrf_coverage.php');
        $this->assertSame(0, $result['exit'], $result['output']);
        $this->assertStringContainsString('CSRF coverage check passed', $result['output']);
        $this->assertStringContainsString('Scanned', $result['output']);
    }
}
