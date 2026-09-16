<?php

declare(strict_types=1);

namespace Tests\Unit\Includes;

use PHPUnit\Framework\TestCase;

class TodoVisibilityTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('ITM_CLI_SCRIPT')) {
            define('ITM_CLI_SCRIPT', true);
        }
        require_once ROOT_PATH . 'includes/todo_visibility.php';
    }

    public function testNormalizeSqlAlias(): void
    {
        $this->assertSame('t.', itm_todo_normalize_sql_alias('t'));
        $this->assertSame('', itm_todo_normalize_sql_alias('bad-alias'));
    }

    public function testVisibilitySqlWithAlias(): void
    {
        $sql = itm_todo_visibility_sql('t');
        $this->assertStringContainsString('t.assigned_to_employee_id IS NULL', $sql);
        $this->assertStringContainsString('FIND_IN_SET(?, t.assigned_to_employee_id)', $sql);
        $this->assertStringContainsString('t.created_by = ?', $sql);
    }

    public function testVisibilitySqlLiteral(): void
    {
        $sql = itm_todo_visibility_sql_literal(5, 't');
        $this->assertStringContainsString('FIND_IN_SET(5, t.assigned_to_employee_id)', $sql);
        $this->assertStringContainsString('t.created_by = 5', $sql);
    }

    public function testAppendVisibilityFilter(): void
    {
        $conditions = [];
        $types = '';
        $params = [];
        itm_todo_append_visibility_filter($conditions, $types, $params, 3);

        $this->assertCount(1, $conditions);
        $this->assertSame('ii', $types);
        $this->assertSame([3, 3], $params);
    }
}
