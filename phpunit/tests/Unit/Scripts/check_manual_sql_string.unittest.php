<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../scripts/lib/itm_manual_sql_string_audit.php';

class CheckManualSqlStringUnittest extends ItmScriptCliTestCase
{
    public function testSqlStringConcatIsViolation(): void
    {
        $line = '$sql = "SELECT * FROM employees WHERE id=" . $employeeId;';
        $hits = itm_manual_sql_string_audit_line($line);
        $this->assertNotEmpty($hits);
        $this->assertSame('sql_string_concat', $hits[0]['rule']);
    }

    public function testSqlInterpolationIsViolation(): void
    {
        $line = '$sql = "INSERT INTO users (name) VALUES ({$userName})";';
        $hits = itm_manual_sql_string_audit_line($line);
        $this->assertNotEmpty($hits);
        $this->assertSame('sql_string_concat', $hits[0]['rule']);
    }

    public function testUserInputInMysqliQueryIsViolation(): void
    {
        $line = 'mysqli_query($conn, "SELECT * FROM t WHERE name=\'" . $_GET["q"] . "\'");';
        $hits = itm_manual_sql_string_audit_line($line);
        $this->assertNotEmpty($hits);
        $this->assertSame('sql_user_input_concat', $hits[0]['rule']);
    }

    public function testHttpBuildQueryHrefIsClean(): void
    {
        $line = '$catalogAddProductHref = htmlspecialchars(\'create.php?\' . http_build_query($catalogAddParams), ENT_QUOTES, \'UTF-8\');';
        $hits = itm_manual_sql_string_audit_line($line);
        $this->assertSame([], $hits);
    }

    public function testMysqliPrepareWithPlaceholderIsClean(): void
    {
        $line = '$stmt = mysqli_prepare($conn, "SELECT id FROM employees WHERE company_id = ? AND id = ?");';
        $hits = itm_manual_sql_string_audit_line($line);
        $this->assertSame([], $hits);
    }

    public function testItmRunQueryPassThroughIsClean(): void
    {
        $line = 'itm_run_query($conn, $sql);';
        $hits = itm_manual_sql_string_audit_line($line);
        $this->assertSame([], $hits);
    }

    public function testCrEscapeIdentifierScaffoldIsClean(): void
    {
        $line = '$rows = mysqli_query($conn, \'SELECT * FROM \' . cr_escape_identifier($crud_table) . $where . \' ORDER BY \' . $sortSql);';
        $hits = itm_manual_sql_string_audit_line($line);
        $this->assertSame([], $hits);
    }

    public function testExemptCommentSkipsLine(): void
    {
        $line = '$sql = "SELECT * FROM t WHERE id=" . $id; // itm-manual-sql-exempt: legacy import';
        $hits = itm_manual_sql_string_audit_line($line);
        $this->assertSame([], $hits);
    }

    public function testCliAuditRuns(): void
    {
        $result = $this->runRepoScript('scripts/check_manual_sql_string.php');
        $this->assertContains($result['exit'], [0, 1]);
        $this->assertStringContainsString('manual SQL string pattern', $result['output']);
    }
}
