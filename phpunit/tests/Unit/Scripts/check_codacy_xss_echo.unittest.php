<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../scripts/lib/itm_codacy_xss_echo_audit.php';

class CheckCodacyXssEchoUnittest extends ItmScriptCliTestCase
{
    public function testShortEchoSearchInValueIsViolation(): void
    {
        $line = '<input value="<?= sanitize($searchRaw) ?>" name="search">';
        $hits = itm_codacy_xss_echo_audit_line($line);
        $this->assertNotEmpty($hits);
        $this->assertSame('short_echo_search_attr', $hits[0]['rule']);
    }

    public function testPhpEchoSearchInValueIsClean(): void
    {
        $line = '<input value="<?php echo sanitize($searchRaw); ?>" name="search">';
        $hits = itm_codacy_xss_echo_audit_line($line);
        $this->assertSame([], $hits);
    }

    public function testEchoSanitizeHttpBuildQueryInHrefIsViolation(): void
    {
        $line = '<a href="index.php?<?php echo sanitize(http_build_query($sendLogsBaseQuery)); ?>">';
        $hits = itm_codacy_xss_echo_audit_line($line);
        $this->assertNotEmpty($hits);
        $this->assertSame('echo_sanitize_http_build_query_href', $hits[0]['rule']);
    }

    public function testPreEscapedHrefVariableIsClean(): void
    {
        $line = '<a href="<?php echo $sendLogsStatHrefAll; ?>">';
        $hits = itm_codacy_xss_echo_audit_line($line);
        $this->assertSame([], $hits);
    }

    public function testCanonicalTitleIsExempt(): void
    {
        $line = '<title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name); ?></title>';
        $hits = itm_codacy_xss_echo_audit_line($line);
        $this->assertSame([], $hits);
    }

    public function testExemptCommentSkipsLine(): void
    {
        $line = '<input value="<?= sanitize($search) ?>" itm-codacy-xss-exempt: legacy>';
        $hits = itm_codacy_xss_echo_audit_line($line);
        $this->assertSame([], $hits);
    }

    public function testCliAuditRuns(): void
    {
        $result = $this->runRepoScript('scripts/check_codacy_xss_echo.php');
        $this->assertContains($result['exit'], [0, 1]);
        $this->assertStringContainsString('Codacy-risky echo pattern', $result['output']);
    }
}
