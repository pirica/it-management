<?php

declare(strict_types=1);

namespace Tests\Unit\Includes;

use PHPUnit\Framework\TestCase;

class AlertsVisibilityTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('ITM_CLI_SCRIPT')) {
            define('ITM_CLI_SCRIPT', true);
        }
        require_once ROOT_PATH . 'includes/alerts_visibility.php';
    }

    public function testNormalizeSqlAliasEmpty(): void
    {
        $this->assertSame('', itm_alerts_normalize_sql_alias(''));
        $this->assertSame('', itm_alerts_normalize_sql_alias('   '));
    }

    public function testNormalizeSqlAliasValid(): void
    {
        $this->assertSame('e.', itm_alerts_normalize_sql_alias('e'));
        $this->assertSame('e.', itm_alerts_normalize_sql_alias('e.'));
        $this->assertSame('alerts.', itm_alerts_normalize_sql_alias('alerts'));
    }

    public function testNormalizeSqlAliasRejectsInvalid(): void
    {
        $this->assertSame('', itm_alerts_normalize_sql_alias('e-table'));
        $this->assertSame('', itm_alerts_normalize_sql_alias('1bad'));
    }

    public function testVisibilitySqlWithoutAlias(): void
    {
        $sql = itm_alerts_visibility_sql();
        $this->assertStringContainsString('assigned_to_user_id IS NULL', $sql);
        $this->assertStringContainsString('assigned_to_user_id = ?', $sql);
        $this->assertStringContainsString('created_by_user_id = ?', $sql);
    }

    public function testVisibilitySqlWithAlias(): void
    {
        $sql = itm_alerts_visibility_sql('e');
        $this->assertStringContainsString('e.assigned_to_user_id IS NULL', $sql);
        $this->assertStringContainsString('e.assigned_to_user_id = ?', $sql);
        $this->assertStringContainsString('e.created_by_user_id = ?', $sql);
    }

    public function testVisibilitySqlLiteral(): void
    {
        $sql = itm_alerts_visibility_sql_literal(42, 'a');
        $this->assertStringContainsString('a.assigned_to_user_id IS NULL', $sql);
        $this->assertStringContainsString('a.assigned_to_user_id = 42', $sql);
        $this->assertStringContainsString('a.created_by_user_id = 42', $sql);
    }

    public function testAppendVisibilityFilter(): void
    {
        $conditions = ['company_id = ?'];
        $types = 'i';
        $params = [1];
        itm_alerts_append_visibility_filter($conditions, $types, $params, 7, 'e');

        $this->assertCount(2, $conditions);
        $this->assertSame('iii', $types);
        $this->assertSame([1, 7, 7], $params);
        $this->assertStringContainsString('e.assigned_to_user_id IS NULL', $conditions[1]);
    }
}
