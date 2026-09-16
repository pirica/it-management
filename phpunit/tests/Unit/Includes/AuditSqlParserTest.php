<?php

namespace Tests\Unit\Includes;

use PHPUnit\Framework\TestCase;

/**
 * Pure DML parser used by itm_run_query() audit hooks — no MySQL required.
 */
class AuditSqlParserTest extends TestCase
{
    protected function setUp(): void
    {
        require_once ROOT_PATH . 'includes/audit_functions.php';
    }

    public function testParsesInsertUpdateDelete(): void
    {
        $insert = itm_parse_audit_sql("INSERT INTO departments (company_id, name) VALUES (1, 'IT')");
        $this->assertSame(['action' => 'INSERT', 'table' => 'departments', 'record_id' => 0], $insert);

        $update = itm_parse_audit_sql('UPDATE `equipment` SET hostname = ? WHERE id = 42');
        $this->assertSame(['action' => 'UPDATE', 'table' => 'equipment', 'record_id' => 42], $update);

        $delete = itm_parse_audit_sql('DELETE FROM tickets WHERE id = 7 AND company_id = 1');
        $this->assertSame(['action' => 'DELETE', 'table' => 'tickets', 'record_id' => 7], $delete);
    }

    public function testReturnsNullForAuditLogsOrNonDml(): void
    {
        $this->assertNull(itm_parse_audit_sql('INSERT INTO audit_logs (company_id) VALUES (1)'));
        $this->assertNull(itm_parse_audit_sql('SELECT * FROM employees WHERE id = 1'));
        $this->assertNull(itm_parse_audit_sql(''));
    }
}
