<?php

declare(strict_types=1);

namespace Tests\Unit\Includes;

use PHPUnit\Framework\TestCase;

class ItmScriptEntryGuardTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('ITM_CLI_SCRIPT')) {
            define('ITM_CLI_SCRIPT', true);
        }
        require_once ROOT_PATH . 'includes/itm_script_entry_guard.php';
    }

    public function testIsPhpunitProcessingDuringTestRun(): void
    {
        $this->assertTrue(itm_is_phpunit_processing());
    }

    public function testSkipHttpEntryUnlessDirectUnderCli(): void
    {
        $this->assertTrue(itm_skip_http_entry_unless_direct(__FILE__));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSkipHttpEntryAllowsContractTestConstant(): void
    {
        require_once ROOT_PATH . 'includes/itm_script_entry_guard.php';
        if (!defined('ITM_HTTP_ENDPOINT_CONTRACT_TEST')) {
            define('ITM_HTTP_ENDPOINT_CONTRACT_TEST', true);
        }
        $this->assertFalse(itm_skip_http_entry_unless_direct(__FILE__));
    }

    public function testSkipCliScriptUnlessDirectWhenIncluded(): void
    {
        $this->assertTrue(itm_skip_cli_script_unless_direct(__FILE__));
    }

    public function testSkipViewPartialUnlessContextUnderCli(): void
    {
        $this->assertTrue(itm_skip_view_partial_unless_context(true, __FILE__));
    }

    public function testIsScriptDirectEntryFalseWhenNotEntryScript(): void
    {
        $this->assertFalse(itm_is_script_direct_entry(__FILE__));
    }
}
