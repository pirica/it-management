<?php

use PHPUnit\Framework\TestCase;

final class ItmCrudScalarColumnSearchTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('itm_crud_scalar_column_search_conditions')) {
            require_once __DIR__ . '/../../../../includes/itm_crud_scalar_column_search.php';
        }
    }

    public function testBuildsTextAndDatetimeLikeFragments(): void
    {
        $conditions = itm_crud_scalar_column_search_conditions(
            'visitors_access_log',
            ['visitor_name', 'date_time_in', 'date_time_out'],
            ['date_time_in', 'date_time_out']
        );

        $this->assertCount(5, $conditions);
        $this->assertSame('`visitor_name` LIKE ?', $conditions[0]);
        $this->assertStringContainsString("DATE_FORMAT(`date_time_in`, '%d-%b-%Y %H:%i') LIKE ?", $conditions[1]);
        $this->assertStringContainsString("DATE_FORMAT(`date_time_in`, '%Y-%m-%d %H:%i:%s') LIKE ?", $conditions[2]);
    }

    public function testRejectsUnsafeIdentifiers(): void
    {
        $this->assertSame([], itm_crud_scalar_column_search_conditions('visitors_access_log', ["id; DROP"], []));
        $this->assertSame([], itm_crud_scalar_column_search_conditions('bad-table', ['visitor_name'], []));
    }
}
