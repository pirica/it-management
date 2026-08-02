<?php

use PHPUnit\Framework\TestCase;

class SelectOptionsApiTest extends TestCase
{
    protected function setUp(): void
    {
        if (defined('ITM_CLI_SCRIPT') === false) {
            define('ITM_CLI_SCRIPT', true);
        }
        if (defined('ITM_SKIP_DB_TESTS') === false) {
            define('ITM_SKIP_DB_TESTS', true);
        }
        if (defined('ROOT_PATH') === false) {
            define('ROOT_PATH', realpath(__DIR__ . '/../../../../') . '/');
        }
    }

    public function testPolicyFunctions()
    {
        require_once ROOT_PATH . 'includes/bootstrap_helpers.php';
        require_once ROOT_PATH . 'includes/itm_select_options_policy.php';

        $this->assertTrue(itm_select_options_is_table_allowed('manufacturers'));
        $this->assertFalse(itm_select_options_is_table_allowed('employees'));

        $extra = ['name' => 'Test', 'role_id' => '1', 'password' => 'secret'];
        $filtered = itm_select_options_filter_extra_fields($extra);

        $this->assertArrayHasKey('name', $filtered);
        $this->assertArrayNotHasKey('role_id', $filtered);
        $this->assertArrayNotHasKey('password', $filtered);
    }
}
