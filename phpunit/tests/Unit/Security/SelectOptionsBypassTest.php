<?php

use PHPUnit\Framework\TestCase;

/**
 * Regression tests for Select Options API whitelisting.
 */
class SelectOptionsBypassTest extends TestCase
{
    public function testCompaniesTableIsBlockedFromQuickAdd()
    {
        require_once ROOT_PATH . 'includes/itm_select_options_policy.php';

        $this->assertTrue(itm_select_options_is_table_allowed('manufacturers'));
        $this->assertFalse(itm_select_options_is_table_allowed('employee_roles'));
        $this->assertFalse(
            itm_select_options_is_table_allowed('companies'),
            'The companies table must not be quick-added via Select Options API.'
        );
    }
}
