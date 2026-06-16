<?php

declare(strict_types=1);

namespace Tests\Unit\Includes;

use PHPUnit\Framework\TestCase;

class NotesVisibilityTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('ITM_CLI_SCRIPT')) {
            define('ITM_CLI_SCRIPT', true);
        }
        require_once ROOT_PATH . 'includes/notes_visibility.php';
    }

    public function testNormalizeSqlAlias(): void
    {
        $this->assertSame('n.', itm_notes_normalize_sql_alias('n'));
        $this->assertSame('', itm_notes_normalize_sql_alias(''));
    }

    public function testVisibilitySqlWithAlias(): void
    {
        $sql = itm_notes_visibility_sql('n');
        $this->assertStringContainsString('n.user_id = ?', $sql);
        $this->assertStringContainsString('JSON_CONTAINS(n.shared_with_json', $sql);
    }

    public function testAppendVisibilityFilter(): void
    {
        $conditions = ['company_id = ?'];
        $types = 'i';
        $params = [1];
        itm_notes_append_visibility_filter($conditions, $types, $params, 9, 'n');

        $this->assertCount(2, $conditions);
        $this->assertSame('iii', $types);
        $this->assertSame([1, 9, 9], $params);
    }
}
