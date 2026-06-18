<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class ScriptBrowserNavTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('ITM_CLI_SCRIPT')) {
            define('ITM_CLI_SCRIPT', true);
        }
        require_once __DIR__ . '/../../../../scripts/lib/script_browser_nav.php';
    }

    public function testScriptIsCliSapi(): void
    {
        $this->assertTrue(itm_script_is_cli_sapi());
    }

    public function testScriptRepoRootPath(): void
    {
        $path = itm_script_repo_root_path();
        $this->assertDirectoryExists($path);
        $this->assertFileExists($path . 'config/config.php');
    }

    public function testBrowserNavHtml(): void
    {
        $html = itm_script_browser_nav_html();
        $this->assertStringContainsString('scripts.php', $html);
        $this->assertStringContainsString('itm-script-nav', $html);
    }

    public function testModulePathFromTable(): void
    {
        $this->assertEquals('modules/users/', itm_script_module_path_from_table('users'));
        $this->assertEquals('', itm_script_module_path_from_table(''));
    }

    public function testTableHasModule(): void
    {
        $this->assertTrue(itm_script_table_has_module('users'));
        $this->assertFalse(itm_script_table_has_module('non_existent_table_xyz'));
    }

    public function testModuleRelativeHref(): void
    {
        $this->assertEquals('../modules/users/index.php', itm_script_module_relative_href('users'));
        $this->assertEquals('../modules/users/edit.php', itm_script_module_relative_href('users', 'edit.php'));
    }

    public function testExternalLinkHtml(): void
    {
        $html = itm_script_external_link_html('https://example.com', 'Label');
        $this->assertStringContainsString('href="https://example.com"', $html);
        $this->assertStringContainsString('Label', $html);
        $this->assertStringContainsString('target="_blank"', $html);
    }
}
