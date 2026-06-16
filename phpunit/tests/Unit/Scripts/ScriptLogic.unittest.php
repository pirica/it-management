<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class ScriptLogicUnittest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('ITM_CLI_SCRIPT')) {
            define('ITM_CLI_SCRIPT', true);
        }
    }

    private function safeRequire(string $path)
    {
        ob_start();
        @require_once $path;
        ob_end_clean();
    }

    public function testUtf8FileLibRoundTrip(): void
    {
        $this->safeRequire(__DIR__ . '/../../../../scripts/lib/utf8_file.php');
        $this->assertTrue(function_exists('itm_write_utf8_text_file'));

        $path = sys_get_temp_dir() . '/itm_utf8_test_' . uniqid('', true) . '.txt';
        $content = "Unicode test — café 🧩\n";
        try {
            $this->assertTrue(itm_write_utf8_text_file($path, $content, true));
            $this->assertFileExists($path);
            $raw = (string)file_get_contents($path);
            $this->assertStringStartsWith("\xEF\xBB\xBF", $raw);
            $this->assertSame($content, itm_read_utf8_text_file($path));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testSqlInjectionDetectorLib(): void
    {
        $this->safeRequire(__DIR__ . '/../../../../scripts/lib/sql_injection_detector.php');
        $this->assertTrue(function_exists('itm_has_sql_injection_signature'));
        $matched = [];
        $this->assertTrue(itm_has_sql_injection_signature("' OR 1=1", $matched));
    }

    public function testEquipmentTypeModulesLib(): void
    {
        $this->safeRequire(__DIR__ . '/../../../../scripts/lib/equipment_type_modules.php');
        $this->assertTrue(function_exists('itm_canonical_equipment_is_module_names'));
    }

    public function testScriptBrowserNavLib(): void
    {
        $this->safeRequire(__DIR__ . '/../../../../scripts/lib/script_browser_nav.php');
        $this->assertTrue(function_exists('itm_script_is_cli_sapi'));
        $this->assertTrue(itm_script_is_cli_sapi());
    }

    public function testMbqaStepDisplayLib(): void
    {
        $this->safeRequire(__DIR__ . '/../../../../scripts/lib/mbqa_step_display.php');
        $this->assertTrue(function_exists('mbqa_step_human_result'));
    }
}
